<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\FunctionParameterClosureTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AggregatorCallbackTypeExtension implements FunctionParameterClosureTypeExtension
{
    private const array FUNCTIONS = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
        'Itera\\Pipe\\associate' => true,
    ];

    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return array_key_exists($functionReflection->getName(), self::FUNCTIONS);
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): ?Type {
        $inputType = AggregatorCallContext::inputType($functionCall, $scope);
        if ($inputType === null) {
            return null;
        }

        $returnType = str_ends_with($functionReflection->getName(), '\\associate')
            ? TypeCombinator::union(new IntegerType(), new StringType())
            : new BooleanType();

        return new ClosureType([
            new AggregatorClosureParameter($inputType),
        ], $returnType);
    }
}

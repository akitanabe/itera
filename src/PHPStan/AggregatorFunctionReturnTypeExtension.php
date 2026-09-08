<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use Itera\Map;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;

final class AggregatorFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    private const array FUNCTIONS = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
    ];

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return array_key_exists($functionReflection->getName(), self::FUNCTIONS);
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $inputType = AggregatorCallContext::inputType($functionCall, $scope);
        if ($inputType === null) {
            return null;
        }

        if ($functionReflection->getName() !== 'Itera\\Aggregator\\associate') {
            return new GenericObjectType(Aggregator::class, [$inputType, new BooleanType()]);
        }

        return new GenericObjectType(Aggregator::class, [
            $inputType,
            new GenericObjectType(Map::class, [
                AggregatorKeyType::fromCall($functionCall, $inputType, $scope),
                $inputType,
            ]),
        ]);
    }
}

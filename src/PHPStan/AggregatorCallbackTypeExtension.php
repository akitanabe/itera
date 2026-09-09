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
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AggregatorCallbackTypeExtension implements FunctionParameterClosureTypeExtension
{
    private const array FUNCTIONS = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
        'Itera\\Aggregator\\countBy' => true,
        'Itera\\Aggregator\\filtering' => true,
        'Itera\\Aggregator\\find' => true,
        'Itera\\Aggregator\\groupBy' => true,
        'Itera\\Aggregator\\flatMapping' => true,
        'Itera\\Aggregator\\folding' => true,
        'Itera\\Aggregator\\mapping' => true,
        'Itera\\Aggregator\\partition' => true,
        'Itera\\Aggregator\\scanning' => true,
        'Itera\\Pipe\\associate' => true,
    ];

    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        $name = $functionReflection->getName();

        return (
            array_key_exists($name, self::FUNCTIONS)
            && (
                $name !== 'Itera\\Aggregator\\scanning'
                && $name !== 'Itera\\Aggregator\\folding'
                || $parameter->getName() === 'step'
            )
        );
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): ?Type {
        $name = $functionReflection->getName();
        $stateful = $name === 'Itera\\Aggregator\\scanning' || $name === 'Itera\\Aggregator\\folding';
        $inputType = AggregatorCallContext::callbackInputType(
            $functionCall,
            $scope,
            $stateful ? 1 : 0,
            $stateful ? 1 : 0,
        );
        if ($inputType === null) {
            return null;
        }

        $stateType = $stateful
            ? $scope->getType($functionCall->getArgs()[0]->value)->generalize(GeneralizePrecision::lessSpecific())
            : new MixedType();
        $returnType = match ($name) {
            'Itera\\Aggregator\\associate',
            'Itera\\Aggregator\\countBy',
            'Itera\\Aggregator\\groupBy',
                => TypeCombinator::union(new IntegerType(), new StringType()),
            'Itera\\Pipe\\associate' => TypeCombinator::union(new IntegerType(), new StringType()),
            'Itera\\Aggregator\\all',
            'Itera\\Aggregator\\any',
            'Itera\\Aggregator\\filtering',
            'Itera\\Aggregator\\find',
            'Itera\\Aggregator\\partition',
                => new BooleanType(),
            'Itera\\Aggregator\\flatMapping' => new IterableType(new MixedType(), new MixedType()),
            'Itera\\Aggregator\\folding', 'Itera\\Aggregator\\scanning' => $stateType,
            default => new MixedType(),
        };

        $parameters = $stateful
            ? [new AggregatorClosureParameter($stateType), new AggregatorClosureParameter($inputType)]
            : [new AggregatorClosureParameter($inputType)];

        return new ClosureType($parameters, $returnType);
    }
}

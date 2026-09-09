<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use Itera\Collection;
use Itera\Map;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\ExprHandler\Helper\ClosureTypeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;

final class AggregatorFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    private const array FUNCTIONS = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
        'Itera\\Aggregator\\filtering' => true,
        'Itera\\Aggregator\\flatMapping' => true,
        'Itera\\Aggregator\\folding' => true,
        'Itera\\Aggregator\\mapping' => true,
        'Itera\\Aggregator\\scanning' => true,
    ];

    public function __construct(
        private readonly ClosureTypeResolver $closureTypes,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return array_key_exists($functionReflection->getName(), self::FUNCTIONS);
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
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

        if ($name === 'Itera\\Aggregator\\all' || $name === 'Itera\\Aggregator\\any') {
            return new GenericObjectType(Aggregator::class, [$inputType, new BooleanType()]);
        }

        if ($name === 'Itera\\Aggregator\\filtering') {
            return new GenericObjectType(Aggregator::class, [
                $inputType,
                new GenericObjectType(Collection::class, [$inputType]),
            ]);
        }

        if ($stateful) {
            $stateType = $scope
                ->getType($functionCall->getArgs()[0]->value)
                ->generalize(GeneralizePrecision::lessSpecific());
            $resultType = $name === 'Itera\\Aggregator\\scanning'
                ? new GenericObjectType(Collection::class, [$stateType])
                : $stateType;

            return new GenericObjectType(Aggregator::class, [$inputType, $resultType]);
        }

        if ($name === 'Itera\\Aggregator\\mapping' || $name === 'Itera\\Aggregator\\flatMapping') {
            $mappedType = AggregatorMappedType::fromCall($functionCall, $inputType, $scope, $this->closureTypes);
            if ($name === 'Itera\\Aggregator\\flatMapping') {
                $mappedType = $mappedType->getIterableValueType();
            }

            return new GenericObjectType(Aggregator::class, [
                $inputType,
                new GenericObjectType(Collection::class, [$mappedType]),
            ]);
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

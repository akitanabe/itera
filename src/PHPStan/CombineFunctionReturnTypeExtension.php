<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class CombineFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Itera\\Aggregator\\combine';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $_functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): Type {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $fixedChildTypes = [];
        $dynamicChildTypes = [];

        foreach ($functionCall->getArgs() as $argument) {
            $originalArgument = $argument->getAttribute('originalArg');
            $shapeArgument = $originalArgument instanceof Arg ? $originalArgument : $argument;
            if ($shapeArgument->unpack) {
                $unpackedType = $scope->getType($shapeArgument->value);
                $constantArrays = $unpackedType->getConstantArrays();
                if (count($constantArrays) !== 1) {
                    $dynamicChildTypes[] = $unpackedType->getIterableValueType();

                    continue;
                }
                self::appendConstantArray($builder, $constantArrays[0]);
                array_push($fixedChildTypes, ...$constantArrays[0]->getValueTypes());

                continue;
            }

            if ($shapeArgument->name === null) {
                continue;
            }

            $childType = $scope->getType($shapeArgument->value);
            $builder->setOffsetValueType(new ConstantStringType($shapeArgument->name->toString()), $childType);
            $fixedChildTypes[] = $childType;
        }

        if ($dynamicChildTypes !== []) {
            return new CombinedAggregatorType(
                new ArrayType(new StringType(), TypeCombinator::union(...$fixedChildTypes, ...$dynamicChildTypes)),
            );
        }

        return new CombinedAggregatorType($builder->getArray());
    }

    private static function appendConstantArray(ConstantArrayTypeBuilder $builder, ConstantArrayType $arrayType): void
    {
        $optionalKeys = array_flip($arrayType->getOptionalKeys());
        foreach ($arrayType->getKeyTypes() as $index => $keyType) {
            $builder->setOffsetValueType(
                $keyType,
                $arrayType->getValueTypes()[$index],
                array_key_exists($index, $optionalKeys),
            );
        }
    }
}

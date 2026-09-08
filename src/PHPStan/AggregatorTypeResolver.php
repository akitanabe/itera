<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use Itera\Collection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

final class AggregatorTypeResolver
{
    public static function inputType(Type $childrenType): Type
    {
        $inputs = [];
        $constantArray = self::constantArray($childrenType);
        if ($constantArray === null) {
            $inputs[] = self::childInputType($childrenType->getIterableValueType());
        }
        foreach ($constantArray?->getValueTypes() ?? [] as $childType) {
            $inputs[] = self::childInputType($childType);
        }

        $inputs = array_values(array_filter($inputs, static fn(Type $type): bool => !self::isNeutralMixed($type)));

        return match (count($inputs)) {
            0 => new MixedType(),
            1 => $inputs[0],
            default => new IntersectionType($inputs),
        };
    }

    public static function resultType(Type $childrenType, ?Type $executionInputType = null): Type
    {
        $executionInputType ??= new MixedType();
        $constantArray = self::constantArray($childrenType);
        if ($constantArray === null) {
            return new ArrayType($childrenType->getIterableKeyType(), self::childResultType(
                $childrenType->getIterableValueType(),
                $executionInputType,
            ));
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();
        $optionalKeys = array_flip($constantArray->getOptionalKeys());
        foreach ($constantArray->getKeyTypes() as $index => $keyType) {
            $builder->setOffsetValueType(
                $keyType,
                self::childResultType($constantArray->getValueTypes()[$index], $executionInputType),
                array_key_exists($index, $optionalKeys),
            );
        }

        return $builder->getArray();
    }

    private static function constantArray(Type $type): ?ConstantArrayType
    {
        $constantArrays = $type->getConstantArrays();

        return count($constantArrays) === 1 ? $constantArrays[0] : null;
    }

    private static function childInputType(Type $childType): Type
    {
        if ($childType instanceof UnionType) {
            $inputTypes = array_values(array_filter(
                array_map(self::childInputType(...), $childType->getTypes()),
                static fn(Type $type): bool => !self::isNeutralMixed($type),
            ));

            return match (count($inputTypes)) {
                0 => new MixedType(),
                1 => $inputTypes[0],
                default => new IntersectionType($inputTypes),
            };
        }

        return $childType->getTemplateType(Aggregator::class, 'T');
    }

    private static function isNeutralMixed(Type $type): bool
    {
        return $type::class === MixedType::class && $type->getSubtractedType() === null;
    }

    private static function childResultType(Type $childType, Type $executionInputType): Type
    {
        if ($childType instanceof UnionType) {
            return TypeCombinator::union(...array_map(static fn(Type $type): Type => self::childResultType(
                $type,
                $executionInputType,
            ), $childType->getTypes()));
        }
        if ($childType instanceof CollectAggregatorType) {
            return new GenericObjectType(Collection::class, [$executionInputType]);
        }

        return $childType->getTemplateType(Aggregator::class, 'R');
    }
}

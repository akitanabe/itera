<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\ExprHandler\Helper\ClosureTypeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AggregatorKeyType
{
    public static function fromCall(
        FuncCall $functionCall,
        Type $inputType,
        Scope $scope,
        ClosureTypeResolver $closureTypes,
    ): Type {
        return self::restrictToArrayKey(AggregatorMappedType::fromCall(
            $functionCall,
            $inputType,
            $scope,
            $closureTypes,
        ));
    }

    private static function restrictToArrayKey(Type $type): Type
    {
        return self::arrayKey()->isSuperTypeOf($type)->yes() ? $type : self::arrayKey();
    }

    private static function arrayKey(): Type
    {
        return TypeCombinator::union(new IntegerType(), new StringType());
    }
}

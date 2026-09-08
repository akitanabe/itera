<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\TrinaryLogic;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AggregatorKeyType
{
    public static function fromCall(FuncCall $functionCall, Type $inputType, Scope $scope): Type
    {
        $callback = $functionCall->getArgs()[0]->value ?? null;
        if ($callback === null) {
            return self::arrayKey();
        }

        if (($callback instanceof ArrowFunction || $callback instanceof Closure) && $callback->returnType !== null) {
            return self::restrictToArrayKey($scope->getFunctionType($callback->returnType, false, false));
        }

        $parameter = $callback instanceof ArrowFunction ? $callback->params[0] ?? null : null;
        if (
            $callback instanceof ArrowFunction
            && $parameter?->var instanceof Variable
            && is_string($parameter->var->name)
        ) {
            // @phpstan-ignore phpstanApi.method (The public BC API has no variable-binding operation; nested lexical/data-flow inference requires assignVariable, validated against the locked PHPStan version.)
            $callbackScope = $scope->toMutatingScope()->assignVariable(
                $parameter->var->name,
                $inputType,
                $inputType,
                TrinaryLogic::createYes(),
            );

            return self::restrictToArrayKey($callbackScope->getType($callback->expr));
        }

        $callbackType = $scope->getType($callback);
        if (!$callbackType->isCallable()->yes()) {
            return self::arrayKey();
        }

        $returnType = ParametersAcceptorSelector::combineAcceptors($callbackType->getCallableParametersAcceptors(
            $scope,
        ))->getReturnType();

        return self::restrictToArrayKey($returnType);
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

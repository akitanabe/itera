<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\ExprHandler\Helper\ClosureTypeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

final class AggregatorMappedType
{
    public static function fromCall(
        FuncCall $functionCall,
        Type $inputType,
        Scope $scope,
        ClosureTypeResolver $closureTypes,
    ): Type {
        $callback = $functionCall->getArgs()[0]->value ?? null;
        if ($callback === null) {
            return new MixedType();
        }

        if (($callback instanceof ArrowFunction || $callback instanceof Closure) && $callback->returnType !== null) {
            return $scope->getFunctionType($callback->returnType, false, false);
        }

        if ($callback instanceof ArrowFunction || $callback instanceof Closure) {
            $callbackType = new ClosureType([new AggregatorClosureParameter($inputType)], new MixedType());
            // @phpstan-ignore phpstanApi.method (The public BC API cannot attach a contextual callable parameter while resolving a Closure body; compatibility is verified against the documented supported PHPStan version.)
            $callbackScope = $scope->toMutatingScope()->pushInFunctionCall(
                null,
                new AggregatorClosureParameter($callbackType),
                false,
            );

            // @phpstan-ignore phpstanApi.method (Contextual Closure result inference requires PHPStan's body-aware resolver; compatibility is verified against the documented supported PHPStan version.)
            return $closureTypes->getClosureType($callbackScope, $callback)->getReturnType();
        }

        $callbackType = $scope->getType($callback);
        if (!$callbackType->isCallable()->yes()) {
            return new MixedType();
        }

        return ParametersAcceptorSelector::combineAcceptors($callbackType->getCallableParametersAcceptors(
            $scope,
        ))->getReturnType();
    }
}

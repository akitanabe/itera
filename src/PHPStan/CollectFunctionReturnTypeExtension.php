<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class CollectFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Itera\\Aggregator\\collect';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $_functionReflection,
        FuncCall $_functionCall,
        Scope $_scope,
    ): Type {
        return new CollectAggregatorType();
    }
}

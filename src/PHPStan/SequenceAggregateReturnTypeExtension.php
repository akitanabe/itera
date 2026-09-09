<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Collection;
use Itera\Sequence;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class SequenceAggregateReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Sequence::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'aggregate';
    }

    public function getTypeFromMethodCall(
        MethodReflection $_methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $argument = $methodCall->getArgs()[0] ?? null;
        if ($argument === null) {
            return null;
        }

        $elementType = $scope->getType($methodCall->var)->getTemplateType(Sequence::class, 'T');
        $aggregatorType = $scope->getType($argument->value);
        if ($aggregatorType instanceof CombinedAggregatorType) {
            return AggregatorTypeResolver::resultType($aggregatorType->getChildrenType(), $elementType);
        }
        if ($aggregatorType instanceof FirstAggregatorType) {
            return TypeCombinator::addNull($elementType);
        }
        if (!$aggregatorType instanceof CollectAggregatorType) {
            return null;
        }

        return new GenericObjectType(Collection::class, [$elementType]);
    }
}

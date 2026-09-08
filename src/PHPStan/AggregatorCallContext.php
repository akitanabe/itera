<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Sequence;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class AggregatorCallContext
{
    public static function inputType(FuncCall $functionCall, Scope $scope): ?Type
    {
        $inputType = self::receiverInputType($functionCall, $scope);
        if ($inputType === null) {
            return null;
        }
        $callback = $functionCall->getArgs()[0]->value ?? null;
        if (!$callback instanceof ArrowFunction && !$callback instanceof Closure) {
            return null;
        }

        if (($callback->params[0]->type ?? null) !== null) {
            return null;
        }

        return $inputType;
    }

    public static function receiverInputType(FuncCall $functionCall, Scope $scope): ?Type
    {
        $receiver = $functionCall->getAttribute(AggregatorContextVisitor::RECEIVER_ATTRIBUTE);
        if (!$receiver instanceof Expr) {
            return null;
        }

        $receiverType = $scope->getType($receiver);
        if (!new ObjectType(Sequence::class)->isSuperTypeOf($receiverType)->yes()) {
            return null;
        }

        return $receiverType->getTemplateType(Sequence::class, 'T');
    }
}

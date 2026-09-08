<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

final class AggregatorContextVisitor extends NodeVisitorAbstract
{
    public const string RECEIVER_ATTRIBUTE = 'iteraAggregatorReceiver';

    private const array CALLBACK_FACTORIES = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
        'all' => true,
        'any' => true,
        'associate' => true,
    ];

    public function enterNode(Node $node): ?Node
    {
        $argument = $node instanceof MethodCall ? $node->getArgs()[0] ?? null : null;
        if (
            $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'aggregate'
            && $argument?->value instanceof FuncCall
            && $this->isCallbackFactory($argument->value)
        ) {
            $argument->value->setAttribute(self::RECEIVER_ATTRIBUTE, $node->var);
        }

        return null;
    }

    private function isCallbackFactory(FuncCall $functionCall): bool
    {
        if (!$functionCall->name instanceof Name) {
            return false;
        }

        $resolvedName = $functionCall->name->getAttribute('resolvedName');
        $name = $resolvedName instanceof Name ? $resolvedName->toString() : $functionCall->name->toString();

        return array_key_exists($name, self::CALLBACK_FACTORIES);
    }
}

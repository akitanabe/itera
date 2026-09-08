<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\NodeVisitorAbstract;

final class AggregatorContextVisitor extends NodeVisitorAbstract
{
    public const string RECEIVER_ATTRIBUTE = 'iteraAggregatorReceiver';

    public const array CALLBACK_FACTORIES = [
        'Itera\\Aggregator\\all' => true,
        'Itera\\Aggregator\\any' => true,
        'Itera\\Aggregator\\associate' => true,
    ];

    private readonly ImportedFunctionResolver $functions;

    public function __construct()
    {
        $this->functions = new ImportedFunctionResolver();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->functions->reset();

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        $this->functions->observe($node);
        $argument = $node instanceof MethodCall ? $node->getArgs()[0] ?? null : null;
        if (
            $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'aggregate'
            && $argument?->value instanceof FuncCall
        ) {
            self::applyReceiver($argument->value, $node->var, $this->functions);
        }

        return null;
    }

    public static function applyReceiver(
        FuncCall $functionCall,
        Expr $receiver,
        ImportedFunctionResolver $functions,
    ): void {
        $name = $functions->resolve($functionCall);
        if (array_key_exists($name, self::CALLBACK_FACTORIES)) {
            $functionCall->setAttribute(self::RECEIVER_ATTRIBUTE, $receiver);

            return;
        }
        if ($name !== 'Itera\\Aggregator\\combine') {
            return;
        }

        $functionCall->setAttribute(self::RECEIVER_ATTRIBUTE, $receiver);
        foreach ($functionCall->getArgs() as $child) {
            if ($child->unpack || !$child->value instanceof FuncCall) {
                continue;
            }
            if (!array_key_exists($functions->resolve($child->value), self::CALLBACK_FACTORIES)) {
                continue;
            }
            $child->value->setAttribute(self::RECEIVER_ATTRIBUTE, $receiver);
        }
    }
}

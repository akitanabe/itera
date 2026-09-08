<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

final class PipeAggregatorContextVisitor extends NodeVisitorAbstract
{
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

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if (!$node instanceof FuncCall || !array_key_exists('originalPipeAttrs', $node->getAttributes())) {
            return null;
        }

        $pipeFunction = $node->name;
        if (!$pipeFunction instanceof FuncCall) {
            return null;
        }

        $receiver = $node->getArgs()[0]->value ?? null;
        if (!$receiver instanceof Expr) {
            return null;
        }

        $pipeFactory = self::functionName($pipeFunction);
        if ($pipeFactory !== 'Itera\\Pipe\\aggregate' && $pipeFactory !== 'Itera\\Pipe\\associate') {
            return null;
        }

        $pipeFunction->setAttribute(AggregatorContextVisitor::RECEIVER_ATTRIBUTE, $receiver);
        $definition = $pipeFunction->getArgs()[0]->value ?? null;
        if ($pipeFactory === 'Itera\\Pipe\\aggregate' && $definition instanceof FuncCall) {
            AggregatorContextVisitor::applyReceiver($definition, $receiver, $this->functions);
        }

        return null;
    }

    private static function functionName(FuncCall $functionCall): string
    {
        return $functionCall->name instanceof Name ? $functionCall->name->toString() : '';
    }
}

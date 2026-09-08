<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

final class ImportedFunctionResolver
{
    private string $namespace = '';

    /** @var array<string, string> */
    private array $aliases = [];

    public function reset(): void
    {
        $this->namespace = '';
        $this->aliases = [];
    }

    public function observe(Node $node): void
    {
        if ($node instanceof Namespace_) {
            $this->namespace = $node->name?->toString() ?? '';
            $this->aliases = [];

            return;
        }
        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $this->record($use, $node->type, '');
            }

            return;
        }
        if ($node instanceof GroupUse) {
            foreach ($node->uses as $use) {
                $this->record($use, $node->type, $node->prefix->toString() . '\\');
            }
        }
    }

    public function resolve(FuncCall $functionCall): string
    {
        if (!$functionCall->name instanceof Name) {
            return '';
        }
        if ($functionCall->name instanceof FullyQualified) {
            return $functionCall->name->toString();
        }

        $rawName = $functionCall->name->toString();
        $parts = explode('\\', $rawName);
        $alias = strtolower($parts[0]);
        if (array_key_exists($alias, $this->aliases)) {
            array_shift($parts);

            return $this->aliases[$alias] . ($parts === [] ? '' : '\\' . implode('\\', $parts));
        }
        if (str_contains($rawName, '\\')) {
            return $rawName;
        }

        return $this->namespace === '' ? $rawName : $this->namespace . '\\' . $rawName;
    }

    private function record(UseItem $use, int $groupType, string $prefix): void
    {
        $type = $use->type === Use_::TYPE_UNKNOWN ? $groupType : $use->type;
        if ($type !== Use_::TYPE_FUNCTION) {
            return;
        }

        $name = $prefix . $use->name->toString();
        $alias = $use->alias?->toString() ?? $use->name->getLast();
        $this->aliases[strtolower($alias)] = $name;
    }
}

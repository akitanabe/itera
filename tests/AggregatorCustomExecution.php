<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<int, string> */
final class AggregatorCustomExecution implements AggregatorExecution
{
    /** @var list<int> */
    private array $values = [];

    public function advance(mixed $value): void
    {
        $this->values[] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return implode(',', $this->values);
    }
}

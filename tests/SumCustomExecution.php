<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<int, int> */
final class SumCustomExecution implements AggregatorExecution
{
    private int $sum = 0;

    public function advance(mixed $value): void
    {
        $this->sum += $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return $this->sum;
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<object, bool> */
final class WideCustomExecution implements AggregatorExecution
{
    public function advance(mixed $_value): void {}

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return true;
    }
}

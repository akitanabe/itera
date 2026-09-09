<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<object, object> */
final class CustomObjectResultExecution implements AggregatorExecution
{
    public function advance(mixed $_value): void {}

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return new \stdClass();
    }
}

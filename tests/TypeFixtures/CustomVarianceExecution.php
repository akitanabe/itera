<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<VarianceUser, bool> */
final class CustomVarianceExecution implements AggregatorExecution
{
    /** @return callable(): AggregatorExecution<VarianceUser, bool> */
    public static function factory(): callable
    {
        return static fn(): AggregatorExecution => new self();
    }

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

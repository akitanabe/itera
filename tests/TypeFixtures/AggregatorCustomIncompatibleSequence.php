<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;
use Itera\Sequence;
use Itera\Tests\AggregatorCustomExecution;

final class AggregatorCustomIncompatibleSequence
{
    public function analyse(): string
    {
        $integers = Aggregator::custom(static fn() => new AggregatorCustomExecution());

        // @phpstan-ignore argument.type (This fixture proves that a custom integer input is rejected for strings.)
        return Sequence::from(['value'])->aggregate($integers);
    }
}

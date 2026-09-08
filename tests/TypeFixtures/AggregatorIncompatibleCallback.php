<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;

final class AggregatorIncompatibleCallback
{
    public function analyse(): bool
    {
        // @phpstan-ignore argument.type (This fixture proves that a typed incompatible callback is rejected.)
        return Sequence::from([1])->aggregate(any(static fn(string $value): bool => $value !== ''));
    }
}

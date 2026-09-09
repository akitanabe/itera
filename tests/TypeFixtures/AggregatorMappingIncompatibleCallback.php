<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\mapping;

final class AggregatorMappingIncompatibleCallback
{
    public function analyse(): mixed
    {
        // @phpstan-ignore argument.type (This fixture proves that mapping rejects a callback with an incompatible input type.)
        return Sequence::from([1])->aggregate(mapping(static fn(string $value): int => strlen($value)));
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\scanning;

final class AggregatorScanningUnstableState
{
    public function analyse(): mixed
    {
        return Sequence::from([1])->aggregate(scanning(
            0,
            // @phpstan-ignore argument.type (This fixture proves that a scanning step cannot change its accepted state type.)
            static fn(int $state, int $value): string => (string) ($state + $value),
        ));
    }
}

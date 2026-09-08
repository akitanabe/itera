<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;
use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;

final class AggregatorCombineDynamicIncompatible
{
    /** @return array<string, bool|int> */
    public function analyse(): array
    {
        // @phpstan-ignore argument.type (Every possible child of a dynamic set must accept the Sequence input.)
        return Sequence::from([1])->aggregate(combine(...$this->children()));
    }

    /** @return array<string, Aggregator<int, bool>|Aggregator<string, int>> */
    private function children(): array
    {
        return ['integer' => any(static fn(int $value): bool => $value > 0)];
    }
}

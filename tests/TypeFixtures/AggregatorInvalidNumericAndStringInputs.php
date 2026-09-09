<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\find;
use function Itera\Aggregator\join;
use function Itera\Aggregator\sum;

final class AggregatorInvalidNumericAndStringInputs
{
    public function analyse(): void
    {
        // @phpstan-ignore argument.type (This fixture proves that sum rejects non-numeric sequence values.)
        Sequence::from(['1'])->aggregate(sum());
        // @phpstan-ignore argument.type (This fixture proves that join rejects non-string sequence values.)
        Sequence::from([1])->aggregate(join(','));
        // @phpstan-ignore argument.type (This fixture proves that find rejects a predicate for another input type.)
        Sequence::from([1])->aggregate(find(static fn(string $value): bool => $value !== ''));
    }
}

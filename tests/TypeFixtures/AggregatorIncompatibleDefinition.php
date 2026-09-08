<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;

final class AggregatorIncompatibleDefinition
{
    public function analyse(): bool
    {
        $strings = any(static fn(string $value): bool => $value !== '');

        // @phpstan-ignore argument.type (This fixture proves that an incompatible saved definition is rejected.)
        return Sequence::from([1])->aggregate($strings);
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;

final class AggregatorCombineIncompatibleDefinition
{
    /** @return array{matched: bool} */
    public function analyse(): array
    {
        $strings = combine(matched: any(static fn(string $value): bool => $value !== ''));

        // @phpstan-ignore argument.type (A combined definition must preserve its child input requirement.)
        return Sequence::from([1])->aggregate($strings);
    }
}

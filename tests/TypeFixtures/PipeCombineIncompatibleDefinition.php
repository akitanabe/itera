<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;
use function Itera\Pipe\aggregate;

final class PipeCombineIncompatibleDefinition
{
    /** @return array{matched: bool} */
    public function analyse(): array
    {
        $strings = aggregate(combine(matched: any(static fn(string $value): bool => $value !== '')));

        // @phpstan-ignore argument.type (A saved pipe must preserve a combined child input requirement.)
        return Sequence::from([1]) |> $strings;
    }
}

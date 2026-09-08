<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Map;
use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Pipe\aggregate;
use function Itera\Pipe\associate;

final class PipeIncompatibleInlineCallbacks
{
    public function aggregate(): bool
    {
        // @phpstan-ignore argument.type (This fixture proves that inline aggregate preserves its callback input contract.)
        return Sequence::from([1]) |> aggregate(any(static fn(string $value): bool => $value !== ''));
    }

    /** @return Map<string, string> */
    public function associate(): Map
    {
        // @phpstan-ignore argument.type (This fixture proves that inline associate preserves its selector input contract.)
        return Sequence::from([1]) |> associate(static fn(string $value): string => $value);
    }
}

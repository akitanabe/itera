<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Pipe\aggregate;

final class PipeIncompatibleDefinition
{
    public function analyse(): bool
    {
        $hasText = aggregate(any(static fn(string $value): bool => $value !== ''));

        // @phpstan-ignore argument.type (This fixture proves that a saved pipe rejects an incompatible Sequence.)
        return Sequence::from([1]) |> $hasText;
    }
}

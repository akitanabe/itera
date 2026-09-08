<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;

final class AggregatorInvalidProperty
{
    public function analyse(): bool
    {
        return Sequence::from([new InvalidPropertyUser()])->aggregate(
            // @phpstan-ignore property.notFound (This fixture proves that contextual typing rejects a missing property.)
            any(static fn($user): bool => $user->missing === true),
        );
    }
}

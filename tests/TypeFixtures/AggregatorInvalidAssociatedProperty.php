<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Map;
use Itera\Sequence;

use function Itera\Aggregator\associate;

final class AggregatorInvalidAssociatedProperty
{
    /** @return Map<array-key, InvalidPropertyUser> */
    public function analyse(): Map
    {
        return Sequence::from([new InvalidPropertyUser()])->aggregate(
            // @phpstan-ignore property.notFound, argument.type, argument.templateType (This fixture proves that associate receives the contextual object type.)
            associate(static fn($user) => $user->missing),
        );
    }
}

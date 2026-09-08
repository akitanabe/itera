<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\associate;

final class AggregatorInvalidKey
{
    public function analyse(): mixed
    {
        return Sequence::from([new InvalidPropertyUser()])->aggregate(
            // @phpstan-ignore argument.type, argument.templateType (This fixture proves that associate rejects a non-array-key callback result.)
            associate(static fn($user) => $user->active),
        );
    }
}

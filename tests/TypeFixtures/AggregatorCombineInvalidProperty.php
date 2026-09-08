<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;

final class AggregatorCombineInvalidProperty
{
    /** @return array{invalid: bool} */
    public function analyse(): array
    {
        return Sequence::from([new InvalidPropertyUser()])->aggregate(combine(
            // @phpstan-ignore property.notFound (A callback nested directly in combine must receive Sequence context.)
            invalid: any(static fn($user): bool => $user->missing === true),
        ));
    }
}

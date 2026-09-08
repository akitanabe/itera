<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;

use function Itera\Aggregator\any;

final class AggregatorInvalidVariance
{
    public function analyse(): void
    {
        $usersOnly = any(static fn(VarianceUser $user): bool => $user->active);

        // @phpstan-ignore argument.type (This fixture proves that a narrower input definition cannot accept every object.)
        $this->acceptsEveryObject($usersOnly);
    }

    /** @param Aggregator<object, mixed> $_aggregator */
    private function acceptsEveryObject(Aggregator $_aggregator): void {}
}

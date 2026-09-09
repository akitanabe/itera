<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;

final class AggregatorCustomInvalidVariance
{
    public function analyse(): void
    {
        $usersOnly = Aggregator::custom(CustomVarianceExecution::factory());

        // @phpstan-ignore argument.type (This fixture proves that a narrower custom input cannot accept every object.)
        $this->acceptsEveryObject($usersOnly);
    }

    /** @param Aggregator<object, mixed> $_aggregator */
    private function acceptsEveryObject(Aggregator $_aggregator): void {}
}

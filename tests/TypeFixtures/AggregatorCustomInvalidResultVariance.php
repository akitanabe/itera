<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;

final class AggregatorCustomInvalidResultVariance
{
    public function analyse(): void
    {
        $wideResult = Aggregator::custom(static fn() => new CustomObjectResultExecution());

        // @phpstan-ignore argument.type (This fixture proves that a wider custom result cannot satisfy a narrower result contract.)
        $this->acceptsUserAggregator($wideResult);
    }

    /** @param Aggregator<object, VarianceUser> $_aggregator */
    private function acceptsUserAggregator(Aggregator $_aggregator): void {}
}

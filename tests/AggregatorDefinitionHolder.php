<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\Map;

final class AggregatorDefinitionHolder
{
    /** @var Aggregator<int, Map<int, int>> */
    public Aggregator $definition;
}

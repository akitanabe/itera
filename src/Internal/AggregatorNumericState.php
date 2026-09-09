<?php

declare(strict_types=1);

namespace Itera\Internal;

/** @internal */
final class AggregatorNumericState
{
    public int|float $sum = 0;

    public int $count = 0;
}

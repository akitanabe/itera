<?php

declare(strict_types=1);

namespace Itera\Internal;

/** @internal */
final class AggregatorBooleanState
{
    public function __construct(
        public bool $value,
    ) {}
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

final class AggregatorFailureSwitch
{
    public function __construct(
        public bool $shouldFail,
    ) {}
}

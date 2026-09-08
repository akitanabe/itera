<?php

declare(strict_types=1);

namespace Itera\Tests;

final class AggregatorBuiltInItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}

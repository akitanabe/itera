<?php

declare(strict_types=1);

namespace Itera\Tests;

final readonly class PipeTypeUser
{
    public function __construct(
        public int $id,
        public bool $active,
    ) {}
}

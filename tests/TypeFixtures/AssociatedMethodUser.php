<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

final class AssociatedMethodUser
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function key(): int
    {
        return $this->id;
    }
}

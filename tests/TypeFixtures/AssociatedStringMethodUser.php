<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

final class AssociatedStringMethodUser
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function key(): string
    {
        return $this->id;
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

final class VarianceUser
{
    public function __construct(
        public readonly bool $active = true,
    ) {}
}

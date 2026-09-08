<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

final class AggregatorClosureParameter implements ParameterReflection
{
    public function __construct(
        private readonly Type $type,
    ) {}

    public function getName(): string
    {
        return 'value';
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function passedByReference(): PassedByReference
    {
        return PassedByReference::createNo();
    }

    public function isVariadic(): bool
    {
        return false;
    }

    public function getDefaultValue(): ?Type
    {
        return null;
    }
}

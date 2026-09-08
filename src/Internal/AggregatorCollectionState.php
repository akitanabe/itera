<?php

declare(strict_types=1);

namespace Itera\Internal;

/**
 * @internal
 * @template T
 */
final class AggregatorCollectionState
{
    /** @var list<T> */
    private array $values = [];

    /** @param T $value */
    public function append(mixed $value): void
    {
        $this->values[] = $value;
    }

    /** @return list<T> */
    public function values(): array
    {
        return $this->values;
    }
}

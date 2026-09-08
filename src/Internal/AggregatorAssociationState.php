<?php

declare(strict_types=1);

namespace Itera\Internal;

/**
 * @internal
 * @template TKey of array-key
 * @template TValue
 */
final class AggregatorAssociationState
{
    /** @var array<TKey, TValue> */
    private array $values = [];

    /**
     * @param TKey $key
     * @param TValue $value
     */
    public function put(mixed $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** @return array<TKey, TValue> */
    public function values(): array
    {
        return $this->values;
    }
}

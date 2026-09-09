<?php

declare(strict_types=1);

namespace Itera\Internal;

/**
 * @internal
 * @template TKey of array-key
 * @template T
 */
final class AggregatorGroupState
{
    /** @var array<TKey, list<T>> */
    private array $groups = [];

    /**
     * @param TKey $key
     * @param T $value
     */
    public function append(int|string $key, mixed $value): void
    {
        $this->groups[$key][] = $value;
    }

    /** @return array<TKey, list<T>> */
    public function groups(): array
    {
        return $this->groups;
    }
}

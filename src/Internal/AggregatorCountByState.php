<?php

declare(strict_types=1);

namespace Itera\Internal;

/**
 * @internal
 * @template TKey of array-key
 */
final class AggregatorCountByState
{
    /** @var array<TKey, int> */
    private array $counts = [];

    /** @param TKey $key */
    public function increment(int|string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    /** @return array<TKey, int> */
    public function counts(): array
    {
        return $this->counts;
    }
}

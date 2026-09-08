<?php

declare(strict_types=1);

namespace Itera;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A materialized key/value map.
 *
 * @mago-expect lint:too-many-methods
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 */
final class Map implements IteratorAggregate, Countable
{
    /** @var array<TKey, TValue> */
    private array $values;

    /**
     * @param array<TKey, TValue> $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * @template TKeyInput of array-key
     * @template TValueInput
     * @param array<TKeyInput, TValueInput> $values
     * @return self<TKeyInput, TValueInput>
     */
    public static function from(array $values): self
    {
        return new self($values);
    }

    /**
     * @return self<never, never>
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Returns null for both missing keys and stored null values. Use has() to
     * distinguish those cases.
     *
     * @param TKey $key
     * @return TValue|null
     */
    public function get(int|string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * @param TKey $key
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return Collection<TKey>
     */
    public function keys(): Collection
    {
        /** @var list<TKey> $keys */
        $keys = array_keys($this->values);

        return Collection::from($keys);
    }

    /**
     * @return Collection<TValue>
     */
    public function values(): Collection
    {
        /** @var list<TValue> $values */
        $values = array_values($this->values);

        return Collection::from($values);
    }

    /**
     * @return Collection<array{0: TKey, 1: TValue}>
     */
    public function entries(): Collection
    {
        /** @var list<array{0: TKey, 1: TValue}> $entries */
        $entries = [];
        foreach ($this->values as $key => $value) {
            $entries[] = [$key, $value];
        }

        return Collection::from($entries);
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * @return array<TKey, TValue>
     */
    public function raw(): array
    {
        return $this->values;
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}

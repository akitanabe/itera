<?php

declare(strict_types=1);

namespace Itera;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @mago-expect lint:too-many-methods
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Collection implements IteratorAggregate, Countable
{
    /** @var list<T> */
    private array $values;

    /**
     * @param list<T> $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * @param mixed ...$values
     * @return self<mixed>
     */
    public static function of(mixed ...$values): self
    {
        return new self(array_values($values));
    }

    /**
     * @template U
     * @param iterable<U> $values
     * @return self<U>
     */
    public static function from(iterable $values): self
    {
        if ($values instanceof self) {
            return $values;
        }

        $materialized = is_array($values) ? array_values($values) : iterator_to_array($values, preserve_keys: false);

        return new self($materialized);
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return T|null
     */
    public function at(int $index): mixed
    {
        $normalizedIndex = $index < 0 ? count($this->values) + $index : $index;

        return $this->values[$normalizedIndex] ?? null;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->at(0);
    }

    /**
     * @return T|null
     */
    public function last(): mixed
    {
        return $this->at(-1);
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
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @return Sequence<T>
     */
    public function sequence(): Sequence
    {
        return Sequence::from($this->values);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}

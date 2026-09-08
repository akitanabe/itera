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
     * @param T $value
     */
    public function contains(mixed $value): bool
    {
        return in_array($value, $this->values, strict: true);
    }

    /**
     * @param callable(T, int<0, max>): bool $predicate
     * @return T|null
     */
    public function find(callable $predicate): mixed
    {
        return array_find($this->values, $predicate);
    }

    /**
     * @param T $value
     */
    public function indexOf(mixed $value): ?int
    {
        $index = array_search($value, $this->values, strict: true);

        return $index === false ? null : $index;
    }

    /**
     * @param callable(T, int<0, max>): bool $predicate
     */
    public function findIndex(callable $predicate): ?int
    {
        return array_find_key($this->values, $predicate);
    }

    /**
     * @param callable(T, int<0, max>): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        return array_any($this->values, $predicate);
    }

    /**
     * @param callable(T, int<0, max>): bool $predicate
     */
    public function all(callable $predicate): bool
    {
        return array_all($this->values, $predicate);
    }

    /**
     * @return self<T>
     */
    public function reverse(): self
    {
        return new self(array_reverse($this->values));
    }

    /**
     * @return self<T>
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->values, $offset, $length));
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

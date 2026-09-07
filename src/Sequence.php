<?php

declare(strict_types=1);

namespace Itera;

use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Traversable;
use TypeError;

/**
 * A mutable, single-use sequence; cloning is prohibited. Starting iteration or
 * any terminal operation consumes the sequence immediately. It cannot be
 * reused after early termination or an exception. Source and callback
 * exceptions are propagated unchanged.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Sequence implements IteratorAggregate
{
    /**
     * Operations can change the element type of this same mutable sequence.
     * @var iterable<mixed>
     */
    private iterable $values;

    /** @var iterable<mixed> */
    private iterable $source;

    private bool $consumed = false;

    /**
     * @param iterable<T> $values
     */
    private function __construct(iterable $values)
    {
        $this->source = $values;
        $this->values = $this->iterateSource();
    }

    /**
     * Wraps an iterable without copying it. Existing Sequence instances are
     * returned unchanged; their source is preserved and read from its current
     * position.
     *
     * @template U
     * @param iterable<U> $values
     * @return self<U>
     */
    public static function from(iterable $values): self
    {
        if ($values instanceof self) {
            return $values;
        }

        return new self($values);
    }

    /**
     * Creates a fresh sequence on every call containing the given values.
     *
     * @param mixed ...$values
     * @return self<mixed>
     */
    public static function of(mixed ...$values): self
    {
        return new self(array_values($values));
    }

    /**
     * Creates a fresh empty sequence on every call.
     *
     * @return self<never>
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Lazily replaces each value and returns this same mutable sequence.
     *
     * Static analysis updates the type of the receiver. References that alias
     * the receiver before this call cannot reliably reflect that type change.
     *
     * @template U
     * @param callable(T): U $mapper
     * @return self<U>
     * @phpstan-self-out self<U>
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function map(callable $mapper): self
    {
        $this->assertNotConsumed();
        $this->values = $this->mapValues($this->pipelineValues(), $mapper);

        return $this;
    }

    /**
     * Lazily retains values for which the predicate returns an actual bool true.
     *
     * @param callable(T): bool $predicate
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws TypeError If the predicate returns a non-boolean value.
     */
    public function filter(callable $predicate): self
    {
        $this->assertNotConsumed();
        $this->values = $this->filterValues($this->pipelineValues(), $predicate);

        return $this;
    }

    /**
     * Lazily replaces each value with the values of an iterable and returns
     * this same mutable sequence.
     *
     * Static analysis updates the type of the receiver. References that alias
     * the receiver before this call cannot reliably reflect that type change.
     *
     * @template U
     * @param callable(T): iterable<U> $mapper
     * @return self<U>
     * @phpstan-self-out self<U>
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws TypeError If the mapper returns a non-iterable value.
     */
    public function flatMap(callable $mapper): self
    {
        $this->assertNotConsumed();
        $this->values = $this->flatMapValues($this->pipelineValues(), $mapper);

        return $this;
    }

    /**
     * Lazily limits output to at most the requested number of values.
     *
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws InvalidArgumentException If count is negative.
     */
    public function take(int $count): self
    {
        $this->assertNotConsumed();
        $this->assertNonNegative($count);
        $this->values = $this->takeValues($this->values, $count);

        return $this;
    }

    /**
     * Lazily omits the requested number of values from the start.
     *
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws InvalidArgumentException If count is negative.
     */
    public function drop(int $count): self
    {
        $this->assertNotConsumed();
        $this->assertNonNegative($count);
        $this->values = $this->dropValues($this->values, $count);

        return $this;
    }

    /**
     * Consumes every output value and returns it with consecutive list keys.
     * This operation finishes only when the resulting sequence is finite.
     *
     * @return list<T>
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function toArray(): array
    {
        $values = [];
        foreach ($this->iterate($this->beginConsumption()) as $value) {
            $values[] = $value;
        }

        return $values;
    }

    /**
     * Consumes every output value into a fresh Collection, including for an
     * empty result. This operation finishes only when the result is finite.
     *
     * @return Collection<T>
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function toCollection(): Collection
    {
        $values = [];
        foreach ($this->iterate($this->beginConsumption()) as $value) {
            $values[] = $value;
        }

        return Collection::from($values);
    }

    /**
     * Consumes up to the first output value and returns null when no value is
     * available. A stored null and an empty result both return null.
     *
     * @return T|null
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function first(): mixed
    {
        $values = $this->iterate($this->beginConsumption());
        if (!$values->valid()) {
            return null;
        }

        return $values->current();
    }

    /**
     * Consumes every output value and returns the number produced by the
     * pipeline. This operation finishes only when the result is finite.
     *
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->iterate($this->beginConsumption()) as $_value) {
            ++$count;
        }

        return $count;
    }

    /**
     * Consumes values until the predicate returns actual bool true. The
     * predicate receives each value as its only argument; an empty result is
     * false.
     *
     * @param callable(T): bool $predicate
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws TypeError If the predicate returns a non-boolean value.
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->iterate($this->beginConsumption()) as $value) {
            if ($this->requireBoolean($predicate($value), 'any')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consumes values until the predicate returns actual bool false. The
     * predicate receives each value as its only argument; an empty result is
     * true.
     *
     * @param callable(T): bool $predicate
     * @throws SequenceConsumedException If this sequence was already consumed.
     * @throws TypeError If the predicate returns a non-boolean value.
     */
    public function all(callable $predicate): bool
    {
        foreach ($this->iterate($this->beginConsumption()) as $value) {
            if (!$this->requireBoolean($predicate($value), 'all')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Consumes every output value by applying step to state then value in
     * sequence order. An empty result returns the original initial value.
     *
     * @template S
     * @param S $initial
     * @param callable(S, T): S $step
     * @return S
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function fold(mixed $initial, callable $step): mixed
    {
        $state = $initial;
        foreach ($this->iterate($this->beginConsumption()) as $value) {
            $state = $step($state, $value);
        }

        return $state;
    }

    /**
     * Consumes the sequence immediately when called, resolving input
     * IteratorAggregate instances then. Values from the returned iterator are
     * read lazily. Source exceptions are propagated unchanged, and the
     * sequence cannot be reused after this call.
     *
     * @return Traversable<int, T>
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function getIterator(): Traversable
    {
        return $this->iterate($this->beginConsumption());
    }

    private function __clone(): void {}

    /**
     * @return iterable<T>
     */
    private function beginConsumption(): iterable
    {
        $this->assertNotConsumed();
        $this->consumed = true;

        // Deferring resolution until iteration would skip it for take(0).
        $this->source = $this->resolveSource($this->source);

        return $this->pipelineValues();
    }

    /**
     * @return iterable<T>
     */
    private function pipelineValues(): iterable
    {
        /**
         * Restore the current public element type after type-erased storage.
         * @var iterable<T> $values
         * @mago-expect lint:inline-variable-return
         */
        $values = $this->values;

        return $values;
    }

    /**
     * @return Generator<int, mixed, void, void>
     */
    private function iterateSource(): Generator
    {
        yield from $this->iterate($this->source);
    }

    private function assertNotConsumed(): void
    {
        if ($this->consumed) {
            throw new SequenceConsumedException();
        }
    }

    private function assertNonNegative(int $count): void
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be non-negative.');
        }
    }

    private function requireBoolean(mixed $result, string $operation): bool
    {
        if (!is_bool($result)) {
            throw new TypeError('Sequence ' . $operation . ' predicate must return bool.');
        }

        return $result;
    }

    /**
     * @template V
     * @param iterable<V> $source
     * @return iterable<V>
     */
    private function resolveSource(iterable $source): iterable
    {
        while ($source instanceof IteratorAggregate) {
            $source = $source->getIterator();
        }

        return $source;
    }

    /**
     * @template V
     * @param iterable<V> $source
     * @return Generator<int, V, void, void>
     */
    private function iterate(iterable $source): Generator
    {
        $index = 0;

        if ($source instanceof Iterator) {
            while ($source->valid()) {
                yield $index++ => $source->current();
                $source->next();
            }

            return;
        }

        foreach ($source as $value) {
            yield $index++ => $value;
        }
    }

    /**
     * @template I
     * @template O
     * @param iterable<I> $source
     * @param callable(I): O $mapper
     * @return Generator<int, O, void, void>
     */
    private function mapValues(iterable $source, callable $mapper): Generator
    {
        foreach ($this->iterate($source) as $value) {
            yield $mapper($value);
        }
    }

    /**
     * @template V
     * @param iterable<V> $source
     * @param callable(V): mixed $predicate
     * @return Generator<int, V, void, void>
     */
    private function filterValues(iterable $source, callable $predicate): Generator
    {
        foreach ($this->iterate($source) as $value) {
            $accepted = $predicate($value);
            if (!is_bool($accepted)) {
                throw new TypeError('Sequence filter predicate must return bool.');
            }

            if ($accepted) {
                yield $value;
            }
        }
    }

    /**
     * @template I
     * @param iterable<I> $source
     * @param callable(I): mixed $mapper
     * @return Generator<int, mixed, void, void>
     */
    private function flatMapValues(iterable $source, callable $mapper): Generator
    {
        foreach ($this->iterate($source) as $value) {
            $inner = $mapper($value);
            if (!is_iterable($inner)) {
                throw new TypeError('Sequence flatMap mapper must return iterable.');
            }

            foreach ($this->iterate($this->resolveSource($inner)) as $innerValue) {
                yield $innerValue;
            }
        }
    }

    /**
     * @param iterable<mixed> $source
     * @return Generator<int, mixed, void, void>
     */
    private function takeValues(iterable $source, int $count): Generator
    {
        if ($count === 0) {
            return;
        }

        $taken = 0;
        foreach ($this->iterate($source) as $value) {
            yield $value;
            ++$taken;
            if ($taken === $count) {
                return;
            }
        }
    }

    /**
     * @param iterable<mixed> $source
     * @return Generator<int, mixed, void, void>
     */
    private function dropValues(iterable $source, int $count): Generator
    {
        $dropped = 0;
        foreach ($this->iterate($source) as $value) {
            if ($dropped < $count) {
                ++$dropped;
                continue;
            }

            yield $value;
        }
    }
}

<?php

declare(strict_types=1);

namespace Itera;

use Closure;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Traversable;
use TypeError;

/**
 * A mutable, single-use sequence; cloning is prohibited.
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
    /** @var iterable<T> */
    private iterable $values;

    /** @var Closure(iterable<mixed>): iterable<mixed> */
    private Closure $pipeline;

    private bool $consumed = false;

    /**
     * @param iterable<T> $values
     */
    private function __construct(iterable $values)
    {
        $this->values = $values;
        $this->pipeline = static fn(iterable $source): iterable => $source;
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
        $previous = $this->pipeline;
        $this->pipeline = function (iterable $source) use ($previous, $mapper): iterable {
            /** @var iterable<T> $input */
            $input = $previous($source);

            return $this->mapValues($input, $mapper);
        };

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
        $previous = $this->pipeline;
        $this->pipeline = function (iterable $source) use ($previous, $predicate): iterable {
            /** @var iterable<T> $input */
            $input = $previous($source);

            return $this->filterValues($input, $predicate);
        };

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
        $previous = $this->pipeline;
        $this->pipeline = function (iterable $source) use ($previous, $mapper): iterable {
            /** @var iterable<T> $input */
            $input = $previous($source);

            return $this->flatMapValues($input, $mapper);
        };

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
        $previous = $this->pipeline;
        /**
         * PHPStan 2.2 misreads the equivalent arrow function as void-returning.
         * @mago-expect lint:prefer-arrow-function
         */
        $this->pipeline = function (iterable $source) use ($previous, $count): iterable {
            return $this->takeValues($previous($source), $count);
        };

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
        $previous = $this->pipeline;
        /**
         * PHPStan 2.2 misreads the equivalent arrow function as void-returning.
         * @mago-expect lint:prefer-arrow-function
         */
        $this->pipeline = function (iterable $source) use ($previous, $count): iterable {
            return $this->dropValues($previous($source), $count);
        };

        return $this;
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

        $source = $this->resolveSource($this->values);
        /**
         * The mutable pipeline is deliberately type-erased between operations.
         * @var iterable<T> $values
         * @mago-expect lint:inline-variable-return
         */
        $values = ($this->pipeline)($source);

        return $values;
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

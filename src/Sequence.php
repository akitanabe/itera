<?php

declare(strict_types=1);

namespace Itera;

use Closure;
use Generator;
use InvalidArgumentException;
use Itera\Internal\SequenceInputFrame;
use Itera\Internal\SequenceOperation;
use Itera\Internal\SequencePipeline;
use Itera\Internal\SequenceStep;
use IteratorAggregate;
use Traversable;
use TypeError;

/**
 * A mutable, single-use sequence; cloning is prohibited. Starting iteration or
 * any terminal operation consumes the sequence immediately. It cannot be
 * reused after early termination or an exception. Source and callback
 * exceptions are propagated unchanged.
 *
 * @mago-expect lint:too-many-methods
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Sequence implements IteratorAggregate
{
    /** @var iterable<mixed> */
    private iterable $source;

    /** @var list<Closure(mixed, int): SequenceStep> */
    private array $operations = [];

    private bool $consumed = false;

    private bool $emptyResult = false;

    /**
     * @param iterable<T> $values
     */
    private function __construct(iterable $values)
    {
        $this->source = $values;
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
        $this->operations[] = SequenceOperation::map($mapper);

        return $this;
    }

    /**
     * Lazily retains values for which the predicate result is truthy.
     *
     * @param callable(T): bool $predicate
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function filter(callable $predicate): self
    {
        $this->assertNotConsumed();
        $this->operations[] = SequenceOperation::filter($predicate);

        return $this;
    }

    /**
     * Lazily includes values through the first value for which the predicate
     * result is truthy, then stops reading the source.
     *
     * @param callable(T): bool $predicate
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function until(callable $predicate): self
    {
        $this->assertNotConsumed();
        $this->operations[] = SequenceOperation::until($predicate);

        return $this;
    }

    /**
     * Lazily skips values before the first value for which the predicate result
     * is truthy, then forwards that value and all following values without
     * calling the predicate again.
     *
     * @param callable(T): bool $predicate
     * @return $this
     * @throws SequenceConsumedException If this sequence was already consumed.
     */
    public function skipUntil(callable $predicate): self
    {
        $this->assertNotConsumed();
        $this->operations[] = SequenceOperation::skipUntil($predicate);

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
        $this->operations[] = SequenceOperation::flatMap($mapper);

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
        if ($count === 0) {
            $this->emptyResult = true;

            return $this;
        }

        $this->operations[] = SequenceOperation::take($count);

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
        $this->operations[] = SequenceOperation::drop($count);

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
        foreach ($this->beginConsumption() as $value) {
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
        foreach ($this->beginConsumption() as $value) {
            $values[] = $value;
        }

        return Collection::from($values);
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
        foreach ($this->beginConsumption() as $value) {
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
        return $this->beginConsumption();
    }

    private function __clone(): void {}

    /**
     * @mago-expect lint:inline-variable-return
     * @return Generator<int, T, void, void>
     */
    private function beginConsumption(): Generator
    {
        $this->assertNotConsumed();
        $this->consumed = true;

        // Deferring resolution until iteration would skip it for take(0).
        $this->source = SequenceInputFrame::resolve($this->source);
        $pipeline = new SequencePipeline($this->source, $this->operations, $this->emptyResult);
        $this->operations = [];

        /** @var Generator<int, T, void, void> $iterator */
        $iterator = $pipeline->getIterator();

        return $iterator;
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
}

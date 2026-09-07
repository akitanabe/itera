<?php

declare(strict_types=1);

namespace Itera;

use Generator;
use Iterator;
use IteratorAggregate;
use Traversable;

/**
 * A mutable, single-use sequence; cloning is prohibited.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Sequence implements IteratorAggregate
{
    /** @var iterable<T> */
    private iterable $values;

    private bool $consumed = false;

    /**
     * @param iterable<T> $values
     */
    private function __construct(iterable $values)
    {
        $this->values = $values;
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
        if ($this->consumed) {
            throw new SequenceConsumedException();
        }

        $this->consumed = true;

        return $this->resolveSource($this->values);
    }

    /**
     * @param iterable<T> $source
     * @return iterable<T>
     */
    private function resolveSource(iterable $source): iterable
    {
        while ($source instanceof IteratorAggregate) {
            $source = $source->getIterator();
        }

        return $source;
    }

    /**
     * @param iterable<T> $source
     * @return Generator<int, T, void, void>
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
}

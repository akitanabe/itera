<?php

declare(strict_types=1);

namespace Itera;

use IteratorAggregate;
use Traversable;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Sequence implements IteratorAggregate
{
    /** @var iterable<T> */
    private iterable $values;

    /**
     * @param iterable<T> $values
     */
    private function __construct(iterable $values)
    {
        $this->values = $values;
    }

    /**
     * @template U
     * @param iterable<U> $values
     * @return self<U>
     */
    public static function from(iterable $values): self
    {
        return new self($values);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->values as $value) {
            yield $value;
        }
    }
}

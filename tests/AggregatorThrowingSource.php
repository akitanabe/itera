<?php

declare(strict_types=1);

namespace Itera\Tests;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, int> */
final class AggregatorThrowingSource implements IteratorAggregate
{
    public function __construct(
        private readonly \RuntimeException $exception,
    ) {}

    public function getIterator(): Traversable
    {
        throw $this->exception;
    }
}

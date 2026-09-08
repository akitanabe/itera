<?php

declare(strict_types=1);

namespace Itera\Tests;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, int> */
final class AggregatorRecordingSource implements IteratorAggregate
{
    public bool $resolved = false;

    /** @param list<string> $events */
    public function __construct(
        private array &$events,
    ) {}

    public function getIterator(): Traversable
    {
        $this->resolved = true;

        return new SequenceRecordingIterator([1], $this->events, 'source');
    }
}

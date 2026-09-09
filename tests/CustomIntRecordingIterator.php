<?php

declare(strict_types=1);

namespace Itera\Tests;

/** @implements \Iterator<int, int> */
final class CustomIntRecordingIterator implements \Iterator
{
    private int $position = 0;

    /** @var list<string> */
    public array $events;

    /**
     * @param list<int> $values
     * @param list<string> $events
     */
    public function __construct(
        private readonly array $values,
        array &$events,
        private readonly string $name,
    ) {
        $this->events = &$events;
    }

    public function current(): mixed
    {
        $this->events[] = $this->name . ':current:' . $this->position;

        return $this->values[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->events[] = $this->name . ':next:' . $this->position;
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->events[] = $this->name . ':rewind';
        $this->position = 0;
    }

    public function valid(): bool
    {
        $this->events[] = $this->name . ':valid:' . $this->position;

        return array_key_exists($this->position, $this->values);
    }
}

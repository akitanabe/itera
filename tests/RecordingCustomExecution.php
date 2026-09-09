<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<int, list<int>> */
final class RecordingCustomExecution implements AggregatorExecution
{
    public int $finishCalls = 0;

    /** @var list<string> */
    public array $events;

    /**
     * @param list<int> $values
     * @param list<string> $events
     */
    public function __construct(
        private array &$values,
        array &$events,
        private readonly bool $initialComplete = false,
        private readonly ?int $completeAfter = null,
    ) {
        $this->events = &$events;
    }

    public function advance(mixed $value): void
    {
        $this->values[] = $value;
        $this->events[] = 'advance:' . $value;
    }

    public function isComplete(): bool
    {
        return $this->initialComplete || $this->completeAfter !== null && count($this->values) >= $this->completeAfter;
    }

    /** @return list<int> */
    public function finish(): mixed
    {
        ++$this->finishCalls;

        return $this->values;
    }
}

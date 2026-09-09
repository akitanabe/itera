<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\AggregatorExecution;

/** @implements AggregatorExecution<int, int> */
final class ScriptedCustomExecution implements AggregatorExecution
{
    private int $advanceCalls = 0;

    /** @var list<string> */
    public array $events;

    /**
     * @mago-expect lint:excessive-parameter-list
     * @param list<string> $events
     */
    public function __construct(
        private readonly string $name,
        array &$events,
        private readonly bool $initialComplete = false,
        private readonly ?int $completeAfter = null,
        private readonly ?string $failureStage = null,
        private readonly ?\RuntimeException $failure = null,
        private readonly bool $failIsCompleteAfterAdvance = false,
    ) {
        $this->events = &$events;
        $this->events[] = $name . ':factory';
    }

    public function advance(mixed $value): void
    {
        ++$this->advanceCalls;
        $this->events[] = $this->name . ':advance:' . $value;
        if ($this->failureStage === 'advance') {
            throw $this->failure ?? new \RuntimeException('advance failed');
        }
    }

    public function isComplete(): bool
    {
        $this->events[] = $this->name . ':isComplete';
        if ($this->failureStage === 'isComplete' && (!$this->failIsCompleteAfterAdvance || $this->advanceCalls > 0)) {
            throw $this->failure ?? new \RuntimeException('isComplete failed');
        }

        return $this->initialComplete || $this->completeAfter !== null && $this->advanceCalls >= $this->completeAfter;
    }

    public function finish(): mixed
    {
        $this->events[] = $this->name . ':finish';
        if ($this->failureStage === 'finish') {
            throw $this->failure ?? new \RuntimeException('finish failed');
        }

        return $this->advanceCalls;
    }
}

<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Generator;
use LogicException;

/** @internal */
final class SequencePipeline
{
    /** @var list<Closure(mixed, int): SequenceStep> */
    private array $operations;

    private readonly bool $empty;

    /**
     * @param iterable<mixed> $source
     * @param list<Closure(mixed, int): SequenceStep> $operations
     */
    public function __construct(
        private readonly iterable $source,
        array $operations,
        bool $empty,
    ) {
        $this->empty = $empty;
        $this->operations = $empty ? [] : $operations;
    }

    /**
     * @mago-expect lint:halstead
     * @return Generator<int, mixed, void, void>
     */
    public function getIterator(): Generator
    {
        if ($this->empty) {
            return;
        }

        $frames = [SequenceInputFrame::from($this->source, 0)];
        $positions = array_fill(start_index: 0, count: count($this->operations), value: 0);
        $stoppedThrough = null;
        $outputIndex = 0;

        try {
            while ($frames !== []) {
                $frameIndex = array_key_last($frames);
                $frame = $frames[$frameIndex];
                if ($stoppedThrough !== null && $frame->startOperation <= $stoppedThrough) {
                    array_pop($frames);
                    continue;
                }

                $read = $frame->read();
                if (!$read['found']) {
                    array_pop($frames);
                    continue;
                }

                $value = $read['value'];
                $completed = true;
                $operationCount = count($this->operations);
                for ($operationIndex = $frame->startOperation; $operationIndex < $operationCount; ++$operationIndex) {
                    ++$positions[$operationIndex];
                    $step = $this->operations[$operationIndex]($value, $positions[$operationIndex]);

                    if ($step->kind === SequenceStep::FORWARD) {
                        $value = $step->value;
                        continue;
                    }

                    if ($step->kind === SequenceStep::SKIP) {
                        $completed = false;
                        break;
                    }

                    if ($step->kind === SequenceStep::EXPAND) {
                        if (!is_iterable($step->value)) {
                            throw new LogicException('Expand step must contain an iterable.');
                        }

                        $frames[] = SequenceInputFrame::from($step->value, $operationIndex + 1);
                        $completed = false;
                        break;
                    }

                    if ($step->kind === SequenceStep::STOP_UPSTREAM) {
                        $stoppedThrough = max($stoppedThrough ?? $operationIndex, $operationIndex);
                        $value = $step->value;
                        continue;
                    }

                    throw new LogicException('Unknown sequence step.');
                }

                if ($completed) {
                    yield $outputIndex++ => $value;
                }
            }
        } finally {
            $this->operations = [];
            $frames = [];
        }
    }
}

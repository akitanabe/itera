<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Generator;

/** @internal */
final class SequencePipelineBuilder
{
    /**
     * @param iterable<mixed> $source
     * @param list<(Closure(mixed, int): SequenceStep)|SequenceChunk> $operations
     * @return Generator<int, mixed, void, void>
     */
    public static function build(iterable $source, array $operations): Generator
    {
        $segment = [];
        foreach ($operations as $operation) {
            if ($operation instanceof Closure) {
                $segment[] = $operation;
                continue;
            }

            $pipeline = new SequencePipeline($source, $segment, false);
            $source = new SequenceChunkIterator($pipeline->getIterator(), $operation->size);
            $segment = [];
        }

        return new SequencePipeline($source, $segment, false)->getIterator();
    }

    /**
     * @param iterable<mixed> $source
     * @return Generator<int, mixed, void, void>
     */
    public static function empty(iterable $source): Generator
    {
        return new SequencePipeline($source, [], true)->getIterator();
    }
}

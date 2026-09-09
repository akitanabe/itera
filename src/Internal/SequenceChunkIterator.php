<?php

declare(strict_types=1);

namespace Itera\Internal;

use Generator;
use Itera\Collection;
use IteratorAggregate;

/**
 * @internal
 * @implements IteratorAggregate<int, Collection<mixed>>
 */
final readonly class SequenceChunkIterator implements IteratorAggregate
{
    /** @param iterable<mixed> $source */
    public function __construct(
        private iterable $source,
        private int $size,
    ) {}

    /** @return Generator<int, Collection<mixed>, void, void> */
    public function getIterator(): Generator
    {
        $values = [];
        $index = 0;

        foreach ($this->source as $value) {
            $values[] = $value;
            if (count($values) !== $this->size) {
                continue;
            }

            $chunk = Collection::from($values);
            $values = [];
            yield $index++ => $chunk;
            unset($chunk);
        }

        if ($values !== []) {
            yield $index => Collection::from($values);
        }
    }
}

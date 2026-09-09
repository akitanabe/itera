<?php

declare(strict_types=1);

namespace Itera\Internal;

/** @internal */
final readonly class SequenceChunk
{
    public function __construct(
        public int $size,
    ) {}
}

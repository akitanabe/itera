<?php

declare(strict_types=1);

namespace Itera\Pipe;

use Itera\Aggregator;
use Itera\Sequence;

/**
 * @internal
 * @template T
 * @template R
 */
final class AggregateAdapter
{
    /** @param Aggregator<T, R> $aggregator */
    public function __construct(
        private readonly Aggregator $aggregator,
    ) {}

    /**
     * @param Sequence<T> $sequence
     * @return R
     */
    public function __invoke(Sequence $sequence): mixed
    {
        return $sequence->aggregate($this->aggregator);
    }
}

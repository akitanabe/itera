<?php

declare(strict_types=1);

namespace Itera\Pipe;

use Closure;
use Itera\Map;
use Itera\Sequence;

/**
 * @internal
 * @template T
 * @template TKey of array-key
 */
final class AssociateAdapter
{
    /** @var Closure(T): TKey */
    private readonly Closure $keySelector;

    /** @param callable(T): TKey $keySelector */
    public function __construct(callable $keySelector)
    {
        $this->keySelector = Closure::fromCallable($keySelector);
    }

    /**
     * @param Sequence<T> $sequence
     * @return Map<TKey, T>
     */
    public function __invoke(Sequence $sequence): Map
    {
        return $sequence->associate($this->keySelector);
    }
}

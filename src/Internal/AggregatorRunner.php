<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;

/** @internal */
final class AggregatorRunner
{
    /**
     * @template T
     * @template TState
     * @template TResult
     * @param iterable<T> $values
     * @param Closure(): TState $initial
     * @param Closure(TState, T): TState $step
     * @param Closure(TState): bool $complete
     * @param Closure(TState): TResult $finish
     * @return TResult
     */
    public static function execute(
        iterable $values,
        Closure $initial,
        Closure $step,
        Closure $complete,
        Closure $finish,
    ): mixed {
        $state = $initial();

        foreach ($values as $value) {
            $state = $step($state, $value);
            if ($complete($state)) {
                break;
            }
        }

        return $finish($state);
    }
}

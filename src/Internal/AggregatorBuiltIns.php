<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Itera\Collection;
use Itera\Map;

/** @internal */
final class AggregatorBuiltIns
{
    /** @return Closure(): AggregatorExecution<mixed, int> */
    public static function count(): Closure
    {
        return static function (): AggregatorExecution {
            $count = 0;

            return new AggregatorExecution(static function (mixed $_value) use (&$count): bool {
                ++$count;

                return false;
            }, static function () use (&$count): int {
                return $count;
            });
        };
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(): AggregatorExecution<T, bool>
     */
    public static function any(callable $predicate): Closure
    {
        return static function () use ($predicate): AggregatorExecution {
            $matched = false;

            return new AggregatorExecution(static function (mixed $value) use ($predicate, &$matched): bool {
                return $matched = $predicate($value) ? true : false;
            }, static function () use (&$matched): bool {
                return $matched;
            });
        };
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(): AggregatorExecution<T, bool>
     */
    public static function all(callable $predicate): Closure
    {
        return static function () use ($predicate): AggregatorExecution {
            $matched = true;

            return new AggregatorExecution(static function (mixed $value) use ($predicate, &$matched): bool {
                $matched = $predicate($value) ? true : false;

                return !$matched;
            }, static function () use (&$matched): bool {
                return $matched;
            });
        };
    }

    /** @return Closure(): AggregatorExecution<mixed, Collection<mixed>> */
    public static function collect(): Closure
    {
        return static function (): AggregatorExecution {
            $state = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($state): bool {
                    $state->append($value);

                    return false;
                },
                static fn(): Collection => Collection::from($state->values()),
            );
        };
    }

    /**
     * @template T
     * @template TKey of array-key
     * @param callable(T): TKey $keySelector
     * @return Closure(): AggregatorExecution<T, Map<TKey, T>>
     * @mago-expect lint:inline-variable-return
     */
    public static function associate(callable $keySelector): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Map<TKey, T>> $executionFactory */
        $executionFactory = static function () use ($keySelector): AggregatorExecution {
            $state = new AggregatorAssociationState();

            return new AggregatorExecution(
                static function (mixed $value) use ($keySelector, $state): bool {
                    $state->put($keySelector($value), $value);

                    return false;
                },
                static fn(): Map => Map::from($state->values()),
            );
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @template U
     * @param callable(T): U $mapper
     * @return Closure(): AggregatorExecution<T, Collection<U>>
     * @mago-expect lint:inline-variable-return
     */
    public static function mapping(callable $mapper): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Collection<U>> $executionFactory */
        $executionFactory = static function () use ($mapper): AggregatorExecution {
            $state = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($mapper, $state): bool {
                    $state->append($mapper($value));

                    return false;
                },
                static fn(): Collection => Collection::from($state->values()),
            );
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(): AggregatorExecution<T, Collection<T>>
     * @mago-expect lint:inline-variable-return
     */
    public static function filtering(callable $predicate): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Collection<T>> $executionFactory */
        $executionFactory = static function () use ($predicate): AggregatorExecution {
            $state = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($predicate, $state): bool {
                    if ($predicate($value)) {
                        $state->append($value);
                    }

                    return false;
                },
                static fn(): Collection => Collection::from($state->values()),
            );
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @template U
     * @param callable(T): iterable<U> $mapper
     * @return Closure(): AggregatorExecution<T, Collection<U>>
     * @mago-expect lint:inline-variable-return
     */
    public static function flatMapping(callable $mapper): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Collection<U>> $executionFactory */
        $executionFactory = static function () use ($mapper): AggregatorExecution {
            $state = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($mapper, $state): bool {
                    foreach ($mapper($value) as $mapped) {
                        $state->append($mapped);
                    }

                    return false;
                },
                static fn(): Collection => Collection::from($state->values()),
            );
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @template S
     * @param S $initial
     * @param callable(S, T): S $step
     * @return Closure(): AggregatorExecution<T, Collection<S>>
     * @mago-expect lint:inline-variable-return
     */
    public static function scanning(mixed $initial, callable $step): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Collection<S>> $executionFactory */
        $executionFactory = static function () use ($initial, $step): AggregatorExecution {
            $current = $initial;
            $states = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($step, &$current, $states): bool {
                    $current = $step($current, $value);
                    $states->append($current);

                    return false;
                },
                static fn(): Collection => Collection::from($states->values()),
            );
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @template S
     * @param S $initial
     * @param callable(S, T): S $step
     * @return Closure(): AggregatorExecution<T, S>
     * @mago-expect lint:inline-variable-return
     */
    public static function folding(mixed $initial, callable $step): Closure
    {
        /** @var Closure(): AggregatorExecution<T, S> $executionFactory */
        $executionFactory = static function () use ($initial, $step): AggregatorExecution {
            $current = $initial;

            return new AggregatorExecution(static function (mixed $value) use ($step, &$current): bool {
                $current = $step($current, $value);

                return false;
            }, static function () use (&$current): mixed {
                return $current;
            });
        };

        return $executionFactory;
    }
}

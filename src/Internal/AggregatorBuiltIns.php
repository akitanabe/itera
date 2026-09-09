<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Itera\Collection;
use Itera\Map;

/**
 * @internal
 * @mago-expect lint:too-many-methods
 */
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

    /** @return Closure(): AggregatorExecution<int|float, int|float> */
    public static function sum(): Closure
    {
        return static function (): AggregatorExecution {
            $state = new AggregatorNumericState();

            return new AggregatorExecution(
                static function (int|float $value) use ($state): bool {
                    $state->sum += $value;

                    return false;
                },
                static fn(): int|float => $state->sum,
            );
        };
    }

    /** @return Closure(): AggregatorExecution<int|float, int|float|null> */
    public static function min(): Closure
    {
        return self::numericExtreme(static fn(int|float $value, int|float $current): bool => $value < $current);
    }

    /** @return Closure(): AggregatorExecution<int|float, int|float|null> */
    public static function max(): Closure
    {
        return self::numericExtreme(static fn(int|float $value, int|float $current): bool => $value > $current);
    }

    /** @return Closure(): AggregatorExecution<int|float, float|null> */
    public static function average(): Closure
    {
        return static function (): AggregatorExecution {
            $state = new AggregatorNumericState();

            return new AggregatorExecution(
                static function (int|float $value) use ($state): bool {
                    $state->sum += $value;
                    ++$state->count;

                    return false;
                },
                static fn(): ?float => $state->count === 0 ? null : $state->sum / $state->count,
            );
        };
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(): AggregatorExecution<T, T|null>
     * @mago-expect lint:inline-variable-return
     */
    public static function find(callable $predicate): Closure
    {
        /** @var Closure(): AggregatorExecution<T, T|null> $executionFactory */
        $executionFactory = static function () use ($predicate): AggregatorExecution {
            $matched = null;

            return new AggregatorExecution(static function (mixed $value) use ($predicate, &$matched): bool {
                if (!$predicate($value)) {
                    return false;
                }

                $matched = $value;

                return true;
            }, static function () use (&$matched): mixed {
                return $matched;
            });
        };

        return $executionFactory;
    }

    /** @return Closure(): AggregatorExecution<mixed, mixed> */
    public static function first(): Closure
    {
        return static function (): AggregatorExecution {
            $first = null;

            return new AggregatorExecution(static function (mixed $value) use (&$first): bool {
                $first = $value;

                return true;
            }, static function () use (&$first): mixed {
                return $first;
            });
        };
    }

    /** @return Closure(): AggregatorExecution<string, string> */
    public static function join(string $separator): Closure
    {
        return static function () use ($separator): AggregatorExecution {
            $joined = '';
            $first = true;

            return new AggregatorExecution(static function (string $value) use ($separator, &$joined, &$first): bool {
                if (!$first) {
                    $joined .= $separator;
                }
                $joined .= $value;
                $first = false;

                return false;
            }, static function () use (&$joined): string {
                return $joined;
            });
        };
    }

    /** @return Closure(): AggregatorExecution<mixed, Collection<mixed>> */
    public static function unique(): Closure
    {
        return static function (): AggregatorExecution {
            $state = new AggregatorCollectionState();

            return new AggregatorExecution(
                static function (mixed $value) use ($state): bool {
                    if (!in_array($value, $state->values(), strict: true)) {
                        $state->append($value);
                    }

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
     * @return Closure(): AggregatorExecution<T, Map<TKey, Collection<T>>>
     * @mago-expect lint:inline-variable-return
     */
    public static function groupBy(callable $keySelector): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Map<TKey, Collection<T>>> $executionFactory */
        $executionFactory = static function () use ($keySelector): AggregatorExecution {
            /** @var AggregatorGroupState<TKey, T> $state */
            $state = new AggregatorGroupState();

            return new AggregatorExecution(static function (mixed $value) use ($keySelector, $state): bool {
                $state->append($keySelector($value), $value);

                return false;
            }, static function () use ($state): Map {
                /** @var array<TKey, Collection<T>> $groups */
                $groups = [];
                foreach ($state->groups() as $key => $values) {
                    $groups[$key] = Collection::from($values);
                }

                return Map::from($groups);
            });
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(): AggregatorExecution<T, array{matched: Collection<T>, unmatched: Collection<T>}>
     * @mago-expect lint:inline-variable-return
     */
    public static function partition(callable $predicate): Closure
    {
        /** @var Closure(): AggregatorExecution<T, array{matched: Collection<T>, unmatched: Collection<T>}> $executionFactory */
        $executionFactory = static function () use ($predicate): AggregatorExecution {
            $matched = new AggregatorCollectionState();
            $unmatched = new AggregatorCollectionState();

            return new AggregatorExecution(static function (mixed $value) use ($predicate, $matched, $unmatched): bool {
                ($predicate($value) ? $matched : $unmatched)->append($value);

                return false;
            }, static fn(): array => [
                'matched' => Collection::from($matched->values()),
                'unmatched' => Collection::from($unmatched->values()),
            ]);
        };

        return $executionFactory;
    }

    /**
     * @template T
     * @template TKey of array-key
     * @param callable(T): TKey $keySelector
     * @return Closure(): AggregatorExecution<T, Map<TKey, int>>
     * @mago-expect lint:inline-variable-return
     */
    public static function countBy(callable $keySelector): Closure
    {
        /** @var Closure(): AggregatorExecution<T, Map<TKey, int>> $executionFactory */
        $executionFactory = static function () use ($keySelector): AggregatorExecution {
            /** @var AggregatorCountByState<TKey> $state */
            $state = new AggregatorCountByState();

            return new AggregatorExecution(
                static function (mixed $value) use ($keySelector, $state): bool {
                    $state->increment($keySelector($value));

                    return false;
                },
                static fn(): Map => Map::from($state->counts()),
            );
        };

        return $executionFactory;
    }

    /**
     * @param Closure(int|float, int|float): bool $better
     * @return Closure(): AggregatorExecution<int|float, int|float|null>
     */
    private static function numericExtreme(Closure $better): Closure
    {
        return static function () use ($better): AggregatorExecution {
            $current = null;

            return new AggregatorExecution(static function (int|float $value) use ($better, &$current): bool {
                if ($current === null || $better($value, $current)) {
                    $current = $value;
                }

                return false;
            }, static function () use (&$current): int|float|null {
                return $current;
            });
        };
    }
}

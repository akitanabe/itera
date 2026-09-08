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
}

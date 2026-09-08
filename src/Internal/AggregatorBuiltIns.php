<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Itera\Collection;
use Itera\Map;

/** @internal */
final class AggregatorBuiltIns
{
    /** @return Closure(iterable<mixed>): int */
    public static function count(): Closure
    {
        return static fn(iterable $values): int => AggregatorRunner::execute(
            $values,
            static fn(): int => 0,
            static fn(int $count, mixed $_value): int => $count + 1,
            static fn(int $_count): bool => false,
            static fn(int $count): int => $count,
        );
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(iterable<T>): bool
     */
    public static function any(callable $predicate): Closure
    {
        return static fn(iterable $values): bool => AggregatorRunner::execute(
            $values,
            static fn(): AggregatorBooleanState => new AggregatorBooleanState(false),
            static function (AggregatorBooleanState $state, mixed $value) use ($predicate): AggregatorBooleanState {
                $state->value = $predicate($value) ? true : false;

                return $state;
            },
            static fn(AggregatorBooleanState $state): bool => $state->value,
            static fn(AggregatorBooleanState $state): bool => $state->value,
        );
    }

    /**
     * @template T
     * @param callable(T): bool $predicate
     * @return Closure(iterable<T>): bool
     */
    public static function all(callable $predicate): Closure
    {
        return static fn(iterable $values): bool => AggregatorRunner::execute(
            $values,
            static fn(): AggregatorBooleanState => new AggregatorBooleanState(true),
            static function (AggregatorBooleanState $state, mixed $value) use ($predicate): AggregatorBooleanState {
                $state->value = $predicate($value) ? true : false;

                return $state;
            },
            static fn(AggregatorBooleanState $state): bool => !$state->value,
            static fn(AggregatorBooleanState $state): bool => $state->value,
        );
    }

    /** @return Closure(iterable<mixed>): Collection<mixed> */
    public static function collect(): Closure
    {
        return static fn(iterable $values): Collection => AggregatorRunner::execute(
            $values,
            static fn(): AggregatorCollectionState => new AggregatorCollectionState(),
            static function (AggregatorCollectionState $state, mixed $value): AggregatorCollectionState {
                $state->append($value);

                return $state;
            },
            static fn(AggregatorCollectionState $_state): bool => false,
            static fn(AggregatorCollectionState $state): Collection => Collection::from($state->values()),
        );
    }

    /**
     * @template T
     * @template TKey of array-key
     * @param callable(T): TKey $keySelector
     * @return Closure(iterable<T>): Map<TKey, T>
     * @mago-expect lint:inline-variable-return
     */
    public static function associate(callable $keySelector): Closure
    {
        /** @var Closure(iterable<T>): Map<TKey, T> $execute */
        $execute = static fn(iterable $values): Map => AggregatorRunner::execute(
            $values,
            static fn(): AggregatorAssociationState => new AggregatorAssociationState(),
            static function (AggregatorAssociationState $state, mixed $value) use (
                $keySelector,
            ): AggregatorAssociationState {
                $state->put($keySelector($value), $value);

                return $state;
            },
            static fn(AggregatorAssociationState $_state): bool => false,
            static fn(AggregatorAssociationState $state): Map => Map::from($state->values()),
        );

        return $execute;
    }
}

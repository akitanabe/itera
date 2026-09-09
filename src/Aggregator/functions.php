<?php

declare(strict_types=1);

namespace Itera\Aggregator;

use Itera\Aggregator;
use Itera\Collection;
use Itera\Internal\AggregatorBuiltIns;
use Itera\Map;

/**
 * Defines an aggregation that counts every value.
 * When executed, it consumes the input completely and returns 0 for empty input.
 *
 * @return Aggregator<mixed, int>
 */
function count(): Aggregator
{
    return Aggregator::countBuiltIn();
}

/**
 * Defines an aggregation that returns true when the predicate is truthy for any value.
 * When executed, evaluation stops at the first truthy result; empty input returns false.
 * PHP runtime truthiness is used while the callable contract remains bool.
 *
 * @template T
 * @param callable(T): bool $predicate
 * @return Aggregator<T, bool>
 */
function any(callable $predicate): Aggregator
{
    return Aggregator::anyBuiltIn($predicate);
}

/**
 * Defines an aggregation that returns true when the predicate is truthy for every value.
 * When executed, evaluation stops at the first falsy result; empty input returns true.
 * PHP runtime truthiness is used while the callable contract remains bool.
 *
 * @template T
 * @param callable(T): bool $predicate
 * @return Aggregator<T, bool>
 */
function all(callable $predicate): Aggregator
{
    return Aggregator::allBuiltIn($predicate);
}

/**
 * Defines an aggregation that collects values in pipeline order.
 * Each execution creates a fresh Collection, and input keys are not preserved.
 *
 * @return Aggregator<mixed, Collection<mixed>>
 */
function collect(): Aggregator
{
    return Aggregator::collectBuiltIn();
}

/**
 * Defines an aggregation that associates values by selected keys.
 * Each execution creates a fresh Map; duplicate keys replace earlier values, so the last wins.
 *
 * @template T
 * @template TKey of array-key
 * @param callable(T): TKey $keySelector
 * @return Aggregator<T, Map<TKey, T>>
 */
function associate(callable $keySelector): Aggregator
{
    return Aggregator::associateBuiltIn($keySelector);
}

/**
 * Defines one flat aggregation from named child definitions.
 * Every child must accept the input element type of the Sequence being aggregated;
 * the combined definition retains the common input constraints of its children.
 *
 * @param Aggregator<never, mixed> ...$aggregators
 * @return Aggregator<mixed, array<string, mixed>>
 */
function combine(Aggregator ...$aggregators): Aggregator
{
    return Aggregator::combineBuiltIn($aggregators);
}

/**
 * Defines an aggregation that maps every input value and collects the results in input order.
 * Each execution creates a fresh Collection; empty input returns an empty Collection.
 *
 * @template T
 * @template U
 * @param callable(T): U $mapper
 * @return Aggregator<T, Collection<U>>
 */
function mapping(callable $mapper): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::mapping($mapper));
}

/**
 * Defines an aggregation that collects values whose predicate result is truthy.
 * PHP runtime truthiness is used while the callable contract remains bool.
 *
 * @template T
 * @param callable(T): bool $predicate
 * @return Aggregator<T, Collection<T>>
 */
function filtering(callable $predicate): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::filtering($predicate));
}

/**
 * Defines an aggregation that flattens each mapped iterable into one Collection.
 * Outer and inner order are preserved, and iterable keys are discarded.
 *
 * @template T
 * @template U
 * @param callable(T): iterable<U> $mapper
 * @return Aggregator<T, Collection<U>>
 * @mago-expect lint:function-name
 */
function flatMapping(callable $mapper): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::flatMapping($mapper));
}

/**
 * Defines an aggregation that collects each updated state without emitting the seed.
 * Seed values are not cloned, so object seeds retain normal PHP reference semantics.
 *
 * @template T
 * @template S
 * @param S $initial
 * @param callable(S, T): S $step
 * @return Aggregator<T, Collection<S>>
 */
function scanning(mixed $initial, callable $step): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::scanning($initial, $step));
}

/**
 * Defines a left-fold aggregation. Empty input returns the initial value.
 * Seed values are not cloned, so object seeds retain normal PHP reference semantics.
 *
 * @template T
 * @template S
 * @param S $initial
 * @param callable(S, T): S $step
 * @return Aggregator<T, S>
 */
function folding(mixed $initial, callable $step): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::folding($initial, $step));
}

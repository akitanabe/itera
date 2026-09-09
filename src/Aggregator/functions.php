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

/**
 * Defines an aggregation that adds numeric values using PHP arithmetic.
 * Empty input returns integer zero; native float, infinity, NaN, and overflow behavior is preserved.
 *
 * @return Aggregator<int|float, int|float>
 */
function sum(): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::sum());
}

/**
 * Defines an aggregation that returns the smallest numeric value, or null for empty input.
 * PHP comparison semantics are used, and the first value is retained when values compare equal.
 *
 * @return Aggregator<int|float, int|float|null>
 */
function min(): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::min());
}

/**
 * Defines an aggregation that returns the largest numeric value, or null for empty input.
 * PHP comparison semantics are used, and the first value is retained when values compare equal.
 *
 * @return Aggregator<int|float, int|float|null>
 */
function max(): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::max());
}

/**
 * Defines an aggregation that returns the arithmetic mean as a float, or null for empty input.
 * Values are added and divided with native PHP numeric semantics.
 *
 * @return Aggregator<int|float, float|null>
 */
function average(): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::average());
}

/**
 * Defines an aggregation that returns the first value with a truthy predicate result.
 * It stops after the first match and returns null when no value matches.
 *
 * @template T
 * @param callable(T): bool $predicate
 * @return Aggregator<T, T|null>
 */
function find(callable $predicate): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::find($predicate));
}

/**
 * Defines an aggregation that returns the first value, including null, then stops.
 * Empty input returns null.
 *
 * @return Aggregator<mixed, mixed>
 */
function first(): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::first());
}

/**
 * Defines an aggregation that joins strings with a separator between every pair of elements.
 * Empty input returns an empty string, and empty string elements still occupy a position.
 *
 * @return Aggregator<string, string>
 */
function join(string $separator): Aggregator
{
    return Aggregator::custom(AggregatorBuiltIns::join($separator));
}

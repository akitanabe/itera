<?php

declare(strict_types=1);

namespace Itera\Pipe;

use Closure;
use Itera\Collection;
use Itera\Sequence;
use Traversable;

/**
 * @return Closure<T>(iterable<T>): Sequence<T>
 */
function sequence(): Closure
{
    return Sequence::from(...);
}

/**
 * @template T
 * @template U
 * @param callable(T): U $mapper
 * @return Closure(Sequence<T>): Sequence<U>
 */
function map(callable $mapper): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->map($mapper);
}

/**
 * @template T
 * @param callable(T): bool $predicate
 * @return Closure(Sequence<T>): Sequence<T>
 */
function filter(callable $predicate): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->filter($predicate);
}

/**
 * @template T
 * @template U
 * @param callable(T): iterable<U> $mapper
 * @return Closure(Sequence<T>): Sequence<U>
 * @mago-expect lint:function-name
 */
function flatMap(callable $mapper): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->flatMap($mapper);
}

/**
 * @template T
 * @param callable(T): bool $predicate
 * @return Closure(Sequence<T>): Sequence<T>
 */
function until(callable $predicate): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->until($predicate);
}

/**
 * @template T
 * @param callable(T): bool $predicate
 * @return Closure(Sequence<T>): Sequence<T>
 * @mago-expect lint:function-name
 */
function skipUntil(callable $predicate): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->skipUntil($predicate);
}

/**
 * @return Closure<T>(Sequence<T>): Sequence<T>
 */
function take(int $count): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->take($count);
}

/**
 * @return Closure<T>(Sequence<T>): Sequence<T>
 */
function drop(int $count): Closure
{
    return static fn(Sequence $sequence): Sequence => $sequence->drop($count);
}

/**
 * @return Closure<T>(Sequence<T>): Collection<T>
 */
function collect(): Closure
{
    // An inline Closure loses the generic result type here. PHPStan preserves
    // the method template when the invokable adapter becomes a Closure.
    $adapter = new class {
        /**
         * @template T
         * @param Sequence<T> $sequence
         * @return Collection<T>
         */
        public function __invoke(Sequence $sequence): Collection
        {
            return $sequence->collect();
        }
    };

    return $adapter(...);
}

/**
 * @template T
 * @template S
 * @param S $initial
 * @param callable(S, T): S $step
 * @return Closure(Sequence<T>): S
 */
function fold(mixed $initial, callable $step): Closure
{
    return static fn(Sequence $sequence): mixed => $sequence->fold($initial, $step);
}

/**
 * @return Closure<T>(Sequence<T>): Traversable<int, T>
 * @mago-expect lint:function-name
 */
function getIterator(): Closure
{
    // An inline Closure loses the generic result type here. PHPStan preserves
    // the method template when the invokable adapter becomes a Closure.
    $adapter = new class {
        /**
         * @template T
         * @param Sequence<T> $sequence
         * @return Traversable<int, T>
         */
        public function __invoke(Sequence $sequence): Traversable
        {
            return $sequence->getIterator();
        }
    };

    return $adapter(...);
}

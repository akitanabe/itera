<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use TypeError;

/** @internal */
final class SequenceOperation
{
    /** @return Closure(mixed, int): SequenceStep */
    public static function map(callable $mapper): Closure
    {
        return static fn(mixed $value, int $_position): SequenceStep => SequenceStep::forward($mapper($value));
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function filter(callable $predicate): Closure
    {
        return static fn(mixed $value, int $_position): SequenceStep => $predicate($value)
            ? SequenceStep::forward($value)
            : SequenceStep::skip();
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function until(callable $predicate): Closure
    {
        return static fn(mixed $value, int $_position): SequenceStep => $predicate($value)
            ? SequenceStep::stopUpstream($value)
            : SequenceStep::forward($value);
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function skipUntil(callable $predicate): Closure
    {
        $matched = false;

        return static function (mixed $value, int $_position) use ($predicate, &$matched): SequenceStep {
            if ($matched) {
                return SequenceStep::forward($value);
            }

            if (!$predicate($value)) {
                return SequenceStep::skip();
            }

            $matched = true;

            return SequenceStep::forward($value);
        };
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function flatMap(callable $mapper): Closure
    {
        return static function (mixed $value, int $_position) use ($mapper): SequenceStep {
            $values = $mapper($value);
            if (!is_iterable($values)) {
                throw new TypeError('Sequence flatMap mapper must return iterable.');
            }

            return SequenceStep::expand($values);
        };
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function take(int $count): Closure
    {
        return static fn(mixed $value, int $position): SequenceStep => $position === $count
            ? SequenceStep::stopUpstream($value)
            : SequenceStep::forward($value);
    }

    /** @return Closure(mixed, int): SequenceStep */
    public static function drop(int $count): Closure
    {
        return static fn(mixed $value, int $position): SequenceStep => $position <= $count
            ? SequenceStep::skip()
            : SequenceStep::forward($value);
    }
}

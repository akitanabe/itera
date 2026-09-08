<?php

declare(strict_types=1);

namespace Itera;

use Closure;
use Itera\Internal\AggregatorBuiltIns;

/**
 * A reusable built-in aggregation definition.
 *
 * @template-contravariant T
 * @template-covariant R
 */
final class Aggregator
{
    /** @param Closure(iterable<T>): R $execute */
    private function __construct(
        private readonly Closure $execute,
    ) {}

    /**
     * @internal
     * @return self<mixed, int>
     */
    public static function countBuiltIn(): self
    {
        return new self(AggregatorBuiltIns::count());
    }

    /**
     * @internal
     * @template TInput
     * @param callable(TInput): bool $predicate
     * @return self<TInput, bool>
     */
    public static function anyBuiltIn(callable $predicate): self
    {
        return new self(AggregatorBuiltIns::any($predicate));
    }

    /**
     * @internal
     * @template TInput
     * @param callable(TInput): bool $predicate
     * @return self<TInput, bool>
     */
    public static function allBuiltIn(callable $predicate): self
    {
        return new self(AggregatorBuiltIns::all($predicate));
    }

    /**
     * @internal
     * @return self<mixed, Collection<mixed>>
     */
    public static function collectBuiltIn(): self
    {
        return new self(AggregatorBuiltIns::collect());
    }

    /**
     * @internal
     * @template TInput
     * @template TKey of array-key
     * @param callable(TInput): TKey $keySelector
     * @return self<TInput, Map<TKey, TInput>>
     */
    public static function associateBuiltIn(callable $keySelector): self
    {
        return new self(AggregatorBuiltIns::associate($keySelector));
    }

    /**
     * @internal
     * @param iterable<T> $values
     * @return R
     */
    public function execute(iterable $values): mixed
    {
        return ($this->execute)($values);
    }
}

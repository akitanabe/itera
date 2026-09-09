<?php

declare(strict_types=1);

namespace Itera;

use Closure;
use Itera\AggregatorExecution as PublicAggregatorExecution;
use Itera\Internal\AggregatorBuiltIns;
use Itera\Internal\AggregatorCombinedExecution;
use Itera\Internal\AggregatorRunner;

/**
 * A reusable aggregation definition.
 *
 * @template-contravariant T
 * @template-covariant R
 */
final class Aggregator
{
    /** @param Closure(): PublicAggregatorExecution<T, R> $executionFactory */
    private function __construct(
        private readonly Closure $executionFactory,
        private readonly bool $combined = false,
    ) {}

    /**
     * Defines a reusable custom aggregation.
     *
     * The factory is called once for each execution and must return fresh
     * mutable state for that execution.
     *
     * @template TInput
     * @template TResult
     * @param callable(): PublicAggregatorExecution<TInput, TResult> $executionFactory
     * @return self<TInput, TResult>
     */
    public static function custom(callable $executionFactory): self
    {
        /** @var Closure(): PublicAggregatorExecution<TInput, TResult> $factory */
        $factory = Closure::fromCallable($executionFactory);

        return new self($factory);
    }

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
     * @param array<array-key, self<never, mixed>> $aggregators
     * @return self<never, array<string, mixed>>
     */
    public static function combineBuiltIn(array $aggregators): self
    {
        if ($aggregators === []) {
            throw new \InvalidArgumentException('combine() requires at least one named Aggregator.');
        }

        foreach ($aggregators as $name => $aggregator) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('combine() accepts named Aggregators only.');
            }
            if ($aggregator->combined) {
                throw new \InvalidArgumentException('combine() cannot contain another combined Aggregator.');
            }
        }

        return new self(static function () use ($aggregators): PublicAggregatorExecution {
            $factories = [];
            foreach ($aggregators as $name => $aggregator) {
                $factories[$name] = $aggregator->executionFactory;
            }

            return AggregatorCombinedExecution::create($factories);
        }, true);
    }

    /**
     * @internal
     * @param iterable<T> $values
     * @return R
     */
    public function execute(iterable $values): mixed
    {
        return AggregatorRunner::execute($values, ($this->executionFactory)());
    }
}

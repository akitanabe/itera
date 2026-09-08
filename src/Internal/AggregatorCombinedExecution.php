<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;

/** @internal */
final class AggregatorCombinedExecution
{
    /**
     * @template T
     * @param array<string, Closure(): AggregatorExecution<T, mixed>> $factories
     * @return AggregatorExecution<T, array<string, mixed>>
     */
    public static function create(array $factories): AggregatorExecution
    {
        $executions = [];
        foreach ($factories as $name => $factory) {
            $executions[$name] = $factory();
        }

        return new AggregatorExecution(static function (mixed $value) use ($executions): bool {
            $complete = true;
            foreach ($executions as $execution) {
                if (!$execution->isComplete()) {
                    $execution->advance($value);
                }
                if (!$execution->isComplete()) {
                    $complete = false;
                }
            }

            return $complete;
        }, static function () use ($executions): array {
            $results = [];
            foreach ($executions as $name => $execution) {
                $results[$name] = $execution->finish();
            }

            return $results;
        });
    }
}

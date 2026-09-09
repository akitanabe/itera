<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;
use Itera\AggregatorExecution as PublicAggregatorExecution;
use Itera\Internal\AggregatorExecution as InternalAggregatorExecution;

/** @internal */
final class AggregatorCombinedExecution
{
    /**
     * @template T
     * @param array<string, Closure(): PublicAggregatorExecution<T, mixed>> $factories
     * @return InternalAggregatorExecution<T, array<string, mixed>>
     */
    public static function create(array $factories): InternalAggregatorExecution
    {
        $executions = [];
        foreach ($factories as $name => $factory) {
            $executions[$name] = $factory();
        }

        $complete = true;
        foreach ($executions as $execution) {
            if ($execution->isComplete()) {
                continue;
            }

            $complete = false;
        }

        return new InternalAggregatorExecution(
            static function (mixed $value) use ($executions): bool {
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
            },
            static function () use ($executions): array {
                $results = [];
                foreach ($executions as $name => $execution) {
                    $results[$name] = $execution->finish();
                }

                return $results;
            },
            $complete,
        );
    }
}

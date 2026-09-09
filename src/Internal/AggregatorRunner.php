<?php

declare(strict_types=1);

namespace Itera\Internal;

use Itera\AggregatorExecution;

/** @internal */
final class AggregatorRunner
{
    /**
     * @template T
     * @template TResult
     * @param iterable<T> $values
     * @param AggregatorExecution<T, TResult> $execution
     * @return TResult
     */
    public static function execute(iterable $values, AggregatorExecution $execution): mixed
    {
        if ($execution->isComplete()) {
            return $execution->finish();
        }

        foreach ($values as $value) {
            $execution->advance($value);
            if ($execution->isComplete()) {
                break;
            }
        }

        return $execution->finish();
    }
}

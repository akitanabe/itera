<?php

declare(strict_types=1);

namespace Itera;

/**
 * The mutable state for one execution of an Aggregator definition.
 * `advance()` is called only while the execution is incomplete. `isComplete()`
 * may be called repeatedly and must not move a completed execution back to an
 * incomplete state. On normal execution `finish()` is called once; it is not
 * an exception cleanup hook.
 *
 * @template-contravariant T
 * @template-covariant R
 */
interface AggregatorExecution
{
    /** @param T $value */
    public function advance(mixed $value): void;

    public function isComplete(): bool;

    /** @return R */
    public function finish(): mixed;
}

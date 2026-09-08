<?php

declare(strict_types=1);

namespace Itera\Internal;

use Closure;

/**
 * @internal
 * @template-contravariant T
 * @template-covariant R
 */
final class AggregatorExecution
{
    private bool $complete = false;

    /**
     * @param Closure(T): bool $advance
     * @param Closure(): R $finish
     */
    public function __construct(
        private readonly Closure $advance,
        private readonly Closure $finish,
    ) {}

    /** @param T $value */
    public function advance(mixed $value): void
    {
        if ($this->complete) {
            return;
        }

        $this->complete = ($this->advance)($value);
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /** @return R */
    public function finish(): mixed
    {
        return ($this->finish)();
    }
}

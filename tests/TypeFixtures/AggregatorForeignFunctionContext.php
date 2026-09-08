<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures\Foreign {
    use Itera\Aggregator;

    /**
     * @param callable(mixed): bool $predicate
     * @return Aggregator<mixed, bool>
     */
    function any(callable $predicate): Aggregator
    {
        return \Itera\Aggregator\any($predicate);
    }
}

namespace Itera\Tests\TypeFixtures {
    use Itera\Sequence;

    final class AggregatorForeignFunctionContext
    {
        public function analyse(): bool
        {
            return (
                Sequence::from([new InvalidPropertyUser()])->aggregate(
                    // @phpstan-ignore property.nonObject (A same-named function from another namespace receives no Itera callback context.)
                    Foreign\any(static fn($value): bool => $value->missing === true),
                ) === true
            );
        }
    }
}

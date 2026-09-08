<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Aggregator;
use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;

final class AggregatorCombineGenericInput
{
    /** @return array{child: bool} */
    public function direct(): array
    {
        return $this->directWith(any(static fn(string $value): bool => $value !== ''));
    }

    /** @return array<string, bool|int> */
    public function dynamic(): array
    {
        return $this->dynamicWith([
            'child' => any(static fn(string $value): bool => $value !== ''),
            'count' => \Itera\Aggregator\count(),
        ]);
    }

    /**
     * @template T
     * @param Aggregator<T, bool> $child
     * @return array{child: bool}
     */
    private function directWith(Aggregator $child): array
    {
        // @phpstan-ignore argument.type (A generic combined child must retain its unknown input requirement.)
        return Sequence::from([1])->aggregate(combine(child: $child));
    }

    /**
     * @template T
     * @param array<string, Aggregator<T, bool>|Aggregator<mixed, int>> $children
     * @return array<string, bool|int>
     */
    private function dynamicWith(array $children): array
    {
        // @phpstan-ignore argument.type (Dynamic combined children must retain their generic input requirement.)
        return Sequence::from([1])->aggregate(combine(...$children));
    }
}

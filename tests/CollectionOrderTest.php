<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionOrderTest extends TestCase
{
    public function testReverseReturnsValuesInReverseOrderWithoutChangingTheOriginal(): void
    {
        $original = Collection::from(['first' => 'alpha', 8 => 'beta', 'last' => 'gamma']);

        $reversed = $original->reverse();

        self::assertSame(['gamma', 'beta', 'alpha'], $reversed->toArray());
        self::assertSame(['alpha', 'beta', 'gamma'], $original->toArray());
    }

    public function testSliceSupportsNegativeOffsetsAndLengthsWithoutChangingTheOriginal(): void
    {
        $original = Collection::of('a', 'b', 'c', 'd');

        $fromNegativeOffset = $original->slice(-3, 2);
        $withNegativeLength = $original->slice(1, -1);
        $toEnd = $original->slice(2);

        self::assertSame(['b', 'c'], $fromNegativeOffset->toArray());
        self::assertSame(['b', 'c'], $withNegativeLength->toArray());
        self::assertSame(['c', 'd'], $toEnd->toArray());
        self::assertSame(['a', 'b', 'c', 'd'], $original->toArray());
    }
}

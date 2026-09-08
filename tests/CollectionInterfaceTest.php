<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionInterfaceTest extends TestCase
{
    public function testIterationIsReplayableAndUsesListIndexes(): void
    {
        $collection = Collection::of('first', 'second');

        self::assertSame([0 => 'first', 1 => 'second'], iterator_to_array($collection));
        self::assertSame([0 => 'first', 1 => 'second'], iterator_to_array($collection));
    }

    public function testSequenceTransformsValuesLazilyWithoutChangingTheCollection(): void
    {
        $collection = Collection::from([1, 2]);
        $seen = [];
        $sequence = $collection->sequence()->map(static function (int $value) use (&$seen): int {
            $seen[] = $value;

            return $value * 10;
        });

        self::assertSame([], $seen);
        self::assertSame([10, 20], $sequence->toArray());
        self::assertSame([1, 2], $seen);
        self::assertSame([1, 2], $collection->values());
        self::assertSame([1, 2], $collection->sequence()->toArray());
    }
}

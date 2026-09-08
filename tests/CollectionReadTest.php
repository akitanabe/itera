<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionReadTest extends TestCase
{
    public function testAtSupportsPositiveAndNegativeIndexes(): void
    {
        $collection = Collection::of('first', 'second', 'last');

        self::assertSame('first', $collection->at(0));
        self::assertSame('second', $collection->at(1));
        self::assertSame('last', $collection->at(-1));
        self::assertSame('second', $collection->at(-2));
    }

    public function testAtReturnsNullWhenIndexIsOutOfRange(): void
    {
        $collection = Collection::of('only');

        self::assertNull($collection->at(1));
        self::assertNull($collection->at(-2));
    }

    public function testNullIsStoredAsAnElement(): void
    {
        $collection = Collection::of(null, 'value', null);

        self::assertNull($collection->at(0));
        self::assertNull($collection->last());
        self::assertSame([null, 'value', null], $collection->values());
    }

    public function testFirstAndLastReturnNullForAnEmptyCollection(): void
    {
        $collection = Collection::empty();

        self::assertSame(null, $collection->first());
        self::assertSame(null, $collection->last());
    }

    public function testFirstAndLastReturnTheBoundaryValues(): void
    {
        $collection = Collection::of('first', 'middle', 'last');

        self::assertSame('first', $collection->first());
        self::assertSame('last', $collection->last());
    }

    public function testChangingTheReturnedArrayDoesNotChangeTheCollection(): void
    {
        $collection = Collection::of('original');
        $values = $collection->values();
        $values[0] = 'changed';
        $values[] = 'added';

        self::assertSame(['changed', 'added'], $values);
        self::assertSame(['original'], $collection->values());
    }

    public function testCountAndIsEmptyDescribeMaterializedValues(): void
    {
        $collection = Collection::of('first', 'second');

        self::assertSame(2, $collection->count());
        self::assertSame(2, count($collection));
        self::assertFalse($collection->isEmpty());
        self::assertSame(0, Collection::empty()->count());
    }
}

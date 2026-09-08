<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionQueryTest extends TestCase
{
    public function testContainsAndIndexOfDistinguishValuesByType(): void
    {
        $collection = Collection::of(1, '1', null);

        self::assertTrue($collection->contains(1));
        self::assertTrue($collection->contains('1'));
        self::assertFalse($collection->contains(1.0));
        self::assertSame(0, $collection->indexOf(1));
        self::assertSame(1, $collection->indexOf('1'));
        self::assertNull($collection->indexOf(1.0));
    }

    public function testFindAndFindIndexReturnResultsMatchingThePredicate(): void
    {
        $collection = Collection::from([1, 2, 3]);

        self::assertSame(2, $collection->find(static fn(int $value): bool => $value >= 2));
        self::assertSame(1, $collection->findIndex(static fn(int $value): bool => $value >= 2));
        self::assertNull($collection->find(static fn(int $value): bool => false));
        self::assertNull($collection->findIndex(static fn(int $value): bool => false));
    }

    public function testAnyAndAllReturnResultsDefinedByPredicatesAndHandleEmptyCollections(): void
    {
        $collection = Collection::from([1, 2, 3]);

        self::assertTrue($collection->any(static fn(int $value): bool => $value === 2));
        self::assertFalse($collection->any(static fn(int $value): bool => $value > 3));
        self::assertTrue($collection->all(static fn(int $value): bool => $value > 0));
        self::assertFalse($collection->all(static fn(int $value): bool => $value < 3));

        self::assertFalse(Collection::empty()->any(static fn(): bool => true));
        self::assertTrue(Collection::empty()->all(static fn(): bool => false));
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Map;
use PHPUnit\Framework\TestCase;

final class MapLookupTest extends TestCase
{
    public function testGetReturnsValuesAndNullForMissingKeys(): void
    {
        $map = Map::from(['present' => 'value', 'null' => null]);

        self::assertSame('value', $map->get('present'));
        self::assertNull($map->get('missing'));
        self::assertNull($map->get('null'));
    }

    public function testHasDistinguishesAnExistingNullValueFromAMissingKey(): void
    {
        $map = Map::from(['null' => null]);

        self::assertTrue($map->has('null'));
        self::assertFalse($map->has('missing'));
    }

    public function testCountAndIsEmptyDescribeTheStoredEntries(): void
    {
        $map = Map::from(['key' => 'value', 'null' => null]);

        self::assertSame(2, $map->count());
        self::assertSame(2, count($map));
        self::assertFalse($map->isEmpty());
        self::assertSame(0, count(Map::empty()));
        self::assertTrue(Map::empty()->isEmpty());
    }
}

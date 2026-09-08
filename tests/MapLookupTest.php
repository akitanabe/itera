<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayAccess;
use Countable;
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

    public function testMapIsCountableAndDoesNotImplementArrayAccess(): void
    {
        $map = Map::from(['key' => 'value']);

        self::assertInstanceOf(Countable::class, $map);
        self::assertNotContains(ArrayAccess::class, class_implements(Map::class));
    }

    public function testMapDoesNotExposePositionValueSearchOrTransformOperations(): void
    {
        self::assertNotContains('at', get_class_methods(Map::class));
        self::assertNotContains('first', get_class_methods(Map::class));
        self::assertNotContains('last', get_class_methods(Map::class));
        self::assertNotContains('containsValue', get_class_methods(Map::class));
        self::assertNotContains('find', get_class_methods(Map::class));
        self::assertNotContains('map', get_class_methods(Map::class));
        self::assertNotContains('filter', get_class_methods(Map::class));
        self::assertNotContains('associate', get_class_methods(Map::class));
    }
}

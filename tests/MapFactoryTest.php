<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Map;
use PHPUnit\Framework\TestCase;

final class MapFactoryTest extends TestCase
{
    public function testFromPreservesTheAssociativeArrayShape(): void
    {
        $map = Map::from([
            'first' => 'alpha',
            7 => 'beta',
        ]);

        self::assertSame(['first' => 'alpha', 7 => 'beta'], $map->raw());
        self::assertSame(['first' => 'alpha', 7 => 'beta'], iterator_to_array($map));
    }

    public function testEmptyCreatesAnEmptyMap(): void
    {
        $first = Map::empty();
        $second = Map::empty();

        self::assertNotSame($first, $second);
        self::assertSame([], $first->raw());
        self::assertTrue($first->isEmpty());
        self::assertSame(0, $first->count());
    }
}

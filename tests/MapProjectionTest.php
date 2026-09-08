<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Map;
use PHPUnit\Framework\TestCase;

final class MapProjectionTest extends TestCase
{
    public function testKeysValuesAndEntriesReturnMaterializedCollections(): void
    {
        $map = Map::from([
            'first' => 'alpha',
            7 => 'beta',
        ]);

        $keys = $map->keys();
        $values = $map->values();
        $entries = $map->entries();

        self::assertInstanceOf(Collection::class, $keys);
        self::assertInstanceOf(Collection::class, $values);
        self::assertInstanceOf(Collection::class, $entries);
        self::assertSame(['first', 7], $keys->values());
        self::assertSame(['alpha', 'beta'], $values->values());
        self::assertSame(
            [
                ['first', 'alpha'],
                [7, 'beta'],
            ],
            $entries->values(),
        );
    }
}

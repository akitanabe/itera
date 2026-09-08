<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Map;
use PHPUnit\Framework\TestCase;

final class CollectionAssociationTest extends TestCase
{
    public function testAssociateUsesOnlyTheKeySelectorAndKeepsTheOriginalValues(): void
    {
        $argumentCounts = [];
        /** @var list<array{id: int, name: string}> $values */
        $values = [
            ['id' => 1, 'name' => 'first'],
            ['id' => 2, 'name' => 'second'],
        ];
        $collection = Collection::from($values);

        $map = $collection->associate(static function (array $value) use (&$argumentCounts): int {
            $argumentCounts[] = func_num_args();

            return $value['id'];
        });

        self::assertInstanceOf(Map::class, $map);
        self::assertSame([1, 1], $argumentCounts);
        self::assertSame(
            [
                1 => ['id' => 1, 'name' => 'first'],
                2 => ['id' => 2, 'name' => 'second'],
            ],
            $map->raw(),
        );
    }

    public function testAssociateUsesTheLastValueForDuplicateKeys(): void
    {
        $map = Collection::of('first', 'replacement')->associate(static fn(): string => 'same');

        self::assertSame(['same' => 'replacement'], $map->raw());
    }

    public function testAssociateOnAnEmptyCollectionReturnsAnEmptyMap(): void
    {
        self::assertSame([], Collection::empty()->associate(static fn(mixed $value): string => 'unused')->raw());
    }
}

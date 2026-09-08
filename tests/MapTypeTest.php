<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Map;
use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;

use function PHPStan\Testing\assertType;

final class MapTypeTest extends TypeInferenceTestCase
{
    public function testInferredTypesMatchTheDeclaredExpectations(): void
    {
        foreach (self::gatherAssertTypes(__FILE__) as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    public function testMapOperationsPreserveKeyAndValueTypes(): void
    {
        /** @var array<string, int> $values */
        $values = ['first' => 1, 'second' => 2];
        $map = Map::from($values);
        $keys = $map->keys();
        $mapValues = $map->values();
        $entries = $map->entries();
        $raw = $map->raw();
        $value = $map->get('first');
        $collectionMap = Collection::from([1, 2])->associate(static fn(int $value): string => (string) $value);
        $sequenceMap = Sequence::from([1, 2])->associate(static fn(int $value): string => (string) $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Map<string, int>', $map);
            assertType('Itera\\Collection<string>', $keys);
            assertType('Itera\\Collection<int>', $mapValues);
            assertType('Itera\\Collection<array{string, int}>', $entries);
            assertType('array<string, int>', $raw);
            assertType('int|null', $value);
            assertType('Itera\\Map<string, int>', $collectionMap);
            assertType('Itera\\Map<string, int>', $sequenceMap);
        }

        self::assertInstanceOf(Collection::class, $keys);
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Generator;
use Itera\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionFactoryTest extends TestCase
{
    public function testOfCreatesAnOrderedCollection(): void
    {
        $collection = Collection::of('first', 'second');

        self::assertSame(['first', 'second'], $collection->toArray());
    }

    public function testEmptyCreatesAnEmptyCollection(): void
    {
        $collection = Collection::empty();

        self::assertSame([], $collection->toArray());
        self::assertTrue($collection->isEmpty());
    }

    public function testOfWithoutValuesCreatesAnEmptyCollection(): void
    {
        self::assertSame([], Collection::of()->toArray());
    }

    public function testFromMaterializesValuesAndDiscardsInputKeys(): void
    {
        $collection = Collection::from([
            'first' => 'alpha',
            7 => 'beta',
        ]);

        self::assertSame(['alpha', 'beta'], $collection->toArray());
        self::assertSame([0 => 'alpha', 1 => 'beta'], iterator_to_array($collection));
    }

    public function testFromReturnsAnExistingCollectionUnchanged(): void
    {
        $collection = Collection::of('value');

        self::assertSame($collection, Collection::from($collection));
    }

    public function testFromConsumesOneShotIterablesAtCallTime(): void
    {
        $finished = false;
        $source = $this->oneShotSource($finished);

        $collection = Collection::from($source);

        self::assertTrue($finished);
        self::assertSame(['first', 'second'], $collection->toArray());
        self::assertSame(['first', 'second'], iterator_to_array($collection));
    }

    private function oneShotSource(bool &$finished): Generator
    {
        yield 'same-key' => 'first';
        yield 'same-key' => 'second';
        $finished = true;
    }
}

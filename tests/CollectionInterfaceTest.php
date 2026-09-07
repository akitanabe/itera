<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayAccess;
use Itera\Collection;
use Itera\Sequence;
use PHPUnit\Framework\TestCase;

final class CollectionInterfaceTest extends TestCase
{
    public function testIterationIsReplayableAndUsesListIndexes(): void
    {
        $collection = Collection::of('first', 'second');

        self::assertSame([0 => 'first', 1 => 'second'], iterator_to_array($collection));
        self::assertSame([0 => 'first', 1 => 'second'], iterator_to_array($collection));
    }

    public function testSequenceIsTheExplicitLazyBoundary(): void
    {
        self::assertInstanceOf(Sequence::class, Collection::of('value')->sequence());
    }

    public function testCollectionDoesNotExposeTransformationOrArrayAccessApis(): void
    {
        $collection = Collection::empty();

        self::assertFalse($this->implementsArrayAccess($collection));
        foreach (['map', 'filter', 'flatMap', 'reduce', 'fold', 'contains', 'equals', 'append', 'prepend'] as $method) {
            self::assertFalse(method_exists($collection, $method), $method);
        }
    }

    private function implementsArrayAccess(object $value): bool
    {
        return $value instanceof ArrayAccess;
    }
}

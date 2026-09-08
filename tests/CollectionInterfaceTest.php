<?php

declare(strict_types=1);

namespace Itera\Tests;

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
}

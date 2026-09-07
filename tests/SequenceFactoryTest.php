<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayObject;
use Generator;
use Itera\Sequence;
use PHPUnit\Framework\TestCase;

final class SequenceFactoryTest extends TestCase
{
    public function testFromKeepsTheSourceLazyUntilTheReturnedIteratorIsConsumed(): void
    {
        $state = new ArrayObject(['started' => false]);
        $source = $this->source($state);

        $sequence = Sequence::from($source);

        self::assertFalse($state['started']);
        $iterator = $sequence->getIterator();
        self::assertFalse($state['started']);
        self::assertSame(['value'], iterator_to_array($iterator));
        self::assertTrue($state['started']);
    }

    public function testFromReturnsTheSameSequenceEvenAfterItWasConsumed(): void
    {
        $sequence = Sequence::of('value');

        self::assertSame($sequence, Sequence::from($sequence));
        iterator_to_array($sequence);
        self::assertSame($sequence, Sequence::from($sequence));
    }

    public function testOfAndEmptyCreateFreshSequences(): void
    {
        $firstOf = Sequence::of('value');
        $secondOf = Sequence::of('value');
        $firstEmpty = Sequence::empty();
        $secondEmpty = Sequence::empty();

        self::assertNotSame($firstOf, $secondOf);
        self::assertNotSame($firstEmpty, $secondEmpty);
        self::assertSame(['value'], iterator_to_array($firstOf));
        self::assertSame([], iterator_to_array($firstEmpty));
    }

    public function testSequenceCannotBeCloned(): void
    {
        $this->expectException(\Error::class);

        clone Sequence::empty();
    }

    /**
     * @param ArrayObject<string, bool> $state
     * @return Generator<int, string, void, void>
     */
    private function source(ArrayObject $state): Generator
    {
        $state['started'] = true;
        yield 'value';
    }
}

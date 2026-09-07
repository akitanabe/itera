<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

final class SequenceConsumptionTest extends TestCase
{
    public function testGettingAnIteratorConsumesTheSequenceImmediately(): void
    {
        $sequence = Sequence::of('value');

        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testASequenceCannotBeReusedAfterAConsumerStopsEarly(): void
    {
        $sequence = Sequence::of('first', 'second');

        foreach ($sequence as $value) {
            self::assertSame('first', $value);
            if ($this->shouldStopAfterFirst($value)) {
                break;
            }
        }

        $this->expectException(SequenceConsumedException::class);
        iterator_to_array($sequence);
    }

    public function testASequenceCannotBeReusedAfterItWasFullyConsumed(): void
    {
        $sequence = Sequence::of('value');

        self::assertSame(['value'], iterator_to_array($sequence));

        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testSourceExceptionsArePropagatedAndTheSequenceRemainsConsumed(): void
    {
        $sourceException = new \RuntimeException('source failed');
        $sequence = Sequence::from($this->throwingSource($sourceException));

        try {
            iterator_to_array($sequence);
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame($sourceException, $exception);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testOutputKeysAreAlwaysAList(): void
    {
        $source = new \ArrayIterator(['first' => 'alpha', 8 => 'beta']);

        self::assertSame([0 => 'alpha', 1 => 'beta'], iterator_to_array(Sequence::from($source)));
    }

    /** @return iterable<int, string> */
    private function throwingSource(\RuntimeException $exception): iterable
    {
        yield 'before';
        throw $exception;
    }

    private function shouldStopAfterFirst(mixed $value): bool
    {
        return $value === 'first';
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayIterator;
use Closure;
use Generator;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Traversable;

final class SequenceSourceTest extends TestCase
{
    public function testAnArrayIteratorIsReadFromItsCurrentPositionWithoutRewinding(): void
    {
        $source = new ArrayIterator(['first', 'second', 'third']);
        $source->next();

        self::assertSame(['second', 'third'], iterator_to_array(Sequence::from($source)));
    }

    public function testAStartedGeneratorIsReadFromItsCurrentPosition(): void
    {
        $source = $this->generator();
        $source->rewind();
        $source->next();

        self::assertSame(['second', 'third'], iterator_to_array(Sequence::from($source)));
    }

    public function testAnUnstartedGeneratorIsReadNormally(): void
    {
        self::assertSame(['first', 'second', 'third'], iterator_to_array(Sequence::from($this->generator())));
    }

    public function testAnExhaustedGeneratorProducesNoValues(): void
    {
        $source = $this->generator();
        iterator_to_array($source);

        self::assertSame([], iterator_to_array(Sequence::from($source)));
    }

    public function testNestedIteratorAggregatesAreResolvedOnceWhenConsumptionStarts(): void
    {
        $inner = new class(new ArrayIterator(['first', 'second'])) implements IteratorAggregate {
            public int $getIteratorCalls = 0;

            /**
             * @param Traversable<int, string> $source
             */
            public function __construct(
                private readonly Traversable $source,
            ) {}

            public function getIterator(): Traversable
            {
                ++$this->getIteratorCalls;

                return $this->source;
            }
        };
        $outer = new class($inner) implements IteratorAggregate {
            public int $getIteratorCalls = 0;

            /**
             * @param Traversable<int, string> $source
             */
            public function __construct(
                private readonly Traversable $source,
            ) {}

            public function getIterator(): Traversable
            {
                ++$this->getIteratorCalls;

                return $this->source;
            }
        };
        $mapped = [];
        $sequence = Sequence::from($outer)->map(static function (mixed $value) use (&$mapped): mixed {
            $mapped[] = $value;
            return $value;
        })->take(1);

        self::assertSame(0, $outer->getIteratorCalls);
        self::assertSame(0, $inner->getIteratorCalls);
        self::assertSame([], $mapped);
        $iterator = $sequence->getIterator();

        self::assertSame(1, $outer->getIteratorCalls);
        self::assertSame(1, $inner->getIteratorCalls);
        self::assertSame([], $mapped);
        self::assertSame(['first'], iterator_to_array($iterator));
        self::assertSame(['first'], $mapped);
        self::assertSame(1, $outer->getIteratorCalls);
        self::assertSame(1, $inner->getIteratorCalls);
    }

    public function testResolvedSourceDoesNotKeepItsAggregateAliveAfterConsumptionStarts(): void
    {
        $source = new class implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                return new ArrayIterator([1]);
            }
        };
        $sourceReference = \WeakReference::create($source);
        $sequence = Sequence::from($source);
        unset($source);

        $iterator = $sequence->getIterator();
        self::assertNull($sourceReference->get());
        self::assertSame([1], iterator_to_array($iterator));
    }

    public function testAnIteratorAggregateExceptionKeepsItsIdentityAndConsumesTheSequence(): void
    {
        $sourceException = new \RuntimeException('cannot create iterator');
        $source = new class($sourceException) implements IteratorAggregate {
            public function __construct(
                private readonly \RuntimeException $exception,
            ) {}

            public function getIterator(): Traversable
            {
                throw $this->exception;
            }
        };
        $sequence = Sequence::from($source);

        try {
            $sequence->getIterator();
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame($sourceException, $exception);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testAnIteratorAggregateCanNotReenterTheSequenceDuringIteratorCreation(): void
    {
        $holder = new class {
            /** @var Sequence<mixed>|null */
            public ?Sequence $sequence = null;
        };
        $callback = static function () use ($holder): Traversable {
            $sequence = $holder->sequence;
            if (!$sequence instanceof Sequence) {
                throw new \LogicException('Sequence was not initialized.');
            }

            return $sequence->getIterator();
        };
        $source = new class($callback) implements IteratorAggregate {
            /**
             * @param Closure(): Traversable<int, mixed> $callback
             */
            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function getIterator(): Traversable
            {
                return ($this->callback)();
            }
        };
        $sequence = Sequence::from($source);
        $holder->sequence = $sequence;

        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    /**
     * @return Generator<int, string, void, void>
     */
    private function generator(): Generator
    {
        yield 'first';
        yield 'second';
        yield 'third';
    }
}

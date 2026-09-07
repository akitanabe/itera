<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayIterator;
use Closure;
use Itera\Sequence;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Traversable;

final class SequenceStreamingTest extends TestCase
{
    public function testCallbacksRunInDeclarationOrderForEachValue(): void
    {
        $trace = [];
        $source = static function () use (&$trace): iterable {
            foreach ([1, 2] as $value) {
                $trace[] = 'source:' . $value;
                yield $value;
            }
        };
        $sequence = Sequence::from($source())
            ->map(static function (int $value) use (&$trace): int {
                $trace[] = 'map:' . $value;
                return $value * 10;
            })
            ->filter(static function (int $value) use (&$trace): bool {
                $trace[] = 'filter:' . $value;
                return true;
            })
            ->flatMap(static function (int $value) use (&$trace): iterable {
                $trace[] = 'flatMap:' . $value;
                return [$value, $value + 1];
            });

        self::assertSame([10, 11, 20, 21], iterator_to_array($sequence));
        self::assertSame(
            [
                'source:1',
                'map:1',
                'filter:10',
                'flatMap:10',
                'source:2',
                'map:2',
                'filter:20',
                'flatMap:20',
            ],
            $trace,
        );
    }

    public function testTakeZeroResolvesAggregateOnceWithoutReadingValues(): void
    {
        $trace = [];
        $factory = static function () use (&$trace): Traversable {
            $trace[] = 'source';
            yield 1;
            throw new \RuntimeException('take(0) read past its boundary.');
        };
        $source = new class($factory) implements IteratorAggregate {
            public int $calls = 0;

            /** @param Closure(): Traversable<int, int> $factory */
            public function __construct(
                private readonly Closure $factory,
            ) {}

            public function getIterator(): Traversable
            {
                ++$this->calls;

                return ($this->factory)();
            }
        };
        $sequence = Sequence::from($source)
            ->drop(1)
            ->flatMap(static function (mixed $value) use (&$trace): iterable {
                $trace[] = 'flatMap';
                return [$value];
            })
            ->take(0);

        self::assertSame([], iterator_to_array($sequence));
        self::assertSame(1, $source->calls);
        self::assertSame([], $trace);
    }

    public function testTakeDoesNotAdvanceUpstreamAfterItsLastValue(): void
    {
        $trace = [];
        $source = static function () use (&$trace): iterable {
            foreach ([1, 2, 3] as $value) {
                $trace[] = $value;
                yield $value;
            }
        };

        self::assertSame([1, 2], iterator_to_array(Sequence::from($source())->take(2)));
        self::assertSame([1, 2], $trace);
    }

    public function testDropFiveThenTakeOneStopsAtTheSixthValue(): void
    {
        $trace = [];
        $source = static function () use (&$trace): iterable {
            for ($value = 1; $value <= 7; ++$value) {
                $trace[] = $value;
                yield $value;
            }
        };

        self::assertSame([6], iterator_to_array(Sequence::from($source())->drop(5)->take(1)));
        self::assertSame([1, 2, 3, 4, 5, 6], $trace);
    }

    public function testFilteringAfterTakeOneDoesNotRequestAnotherValue(): void
    {
        $trace = [];
        $source = static function () use (&$trace): iterable {
            foreach ([1, 2] as $value) {
                $trace[] = $value;
                yield $value;
            }
        };

        $sequence = Sequence::from($source())
            ->take(1)
            ->filter(static fn(): bool => false);

        self::assertSame([], iterator_to_array($sequence));
        self::assertSame([1], $trace);
    }

    public function testTakeTwoStopsInsideTheFirstFlatMappedIterable(): void
    {
        $trace = [];
        $outer = static function () use (&$trace): iterable {
            foreach ([1, 2] as $value) {
                $trace[] = 'outer:' . $value;
                yield $value;
            }
        };
        $inner = static function (int $outerValue) use (&$trace): iterable {
            foreach ([1, 2, 3] as $innerValue) {
                $trace[] = 'inner:' . $outerValue . ':' . $innerValue;
                yield ($outerValue * 10) + $innerValue;
            }
        };

        $sequence = Sequence::from($outer())->flatMap($inner)->take(2);

        self::assertSame([11, 12], iterator_to_array($sequence));
        self::assertSame(['outer:1', 'inner:1:1', 'inner:1:2'], $trace);
    }

    public function testFlatMapReadsAnIteratorFromItsCurrentPosition(): void
    {
        $inner = new ArrayIterator(['first', 'second', 'third']);
        $inner->next();

        self::assertSame(
            ['second', 'third'],
            iterator_to_array(Sequence::of('outer')->flatMap(static fn(): iterable => $inner)),
        );
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayIterator;
use Closure;
use Generator;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use Iterator;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class SequenceStreamingTest extends TestCase
{
    public function testTakeBeforeFlatMapFinishesTheSelectedExpansionWithoutReadingTheNextSourceValue(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2], $events, 'outer');
        $inner = new SequenceRecordingIterator([1, 11], $events, 'inner');

        $values = Sequence::from($source)
            ->take(1)
            ->flatMap(static fn(): iterable => $inner)
            ->collect()
            ->values();

        self::assertSame([1, 11], $values);
        self::assertSame(
            [
                'outer:valid:0',
                'outer:current:0',
                'inner:valid:0',
                'inner:current:0',
                'inner:next:0',
                'inner:valid:1',
                'inner:current:1',
                'inner:next:1',
                'inner:valid:2',
            ],
            $events,
        );
    }

    public function testUntilStopsTheSourceAfterTheMatchingValueWithoutAdvancingIt(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame(
            [1, 2],
            Sequence::from($source)
                ->until(static fn(mixed $value): bool => $value === 2)
                ->collect()
                ->values(),
        );
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testSkipUntilWithTakeStopsAtTheFirstMatchWithoutAdvancingTheSource(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');
        $seen = [];

        self::assertSame(
            [2],
            Sequence::from($source)
                ->skipUntil(static function (mixed $value) use (&$seen): bool {
                    $seen[] = $value;

                    return $value === 2;
                })
                ->take(1)
                ->collect()
                ->values(),
        );
        self::assertSame([1, 2], $seen);
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testSkipUntilStatePersistsAcrossFlatMappedChildren(): void
    {
        $seen = [];

        self::assertSame(
            [11, 2, 12],
            Sequence::from([1, 2])
                ->flatMap(static fn(int $value): iterable => [$value, $value + 10])
                ->skipUntil(static function (int $value) use (&$seen): bool {
                    $seen[] = $value;

                    return $value === 11;
                })
                ->collect()
                ->values(),
        );
        self::assertSame([1, 11], $seen);
    }

    public function testUntilBeforeFlatMapFullyExpandsTheMatchingValueThenStopsTheSource(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame(
            [1, 11, 2, 12],
            Sequence::from($source)
                ->until(static fn(mixed $value): bool => $value === 2)
                ->flatMap(static fn(mixed $value): iterable => $value === 1 ? [$value, 11] : [$value, 12])
                ->collect()
                ->values(),
        );
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testUntilAfterFlatMapStopsInsideTheMatchingExpansionAndThenTheParent(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame(
            [1, 11, 2],
            Sequence::from($source)
                ->flatMap(static function (mixed $value) use (&$events): iterable {
                    $name = $value === 1 ? 'inner:1' : 'inner:2';
                    $values = $value === 1 ? [1, 11] : [2, 12];

                    return new SequenceRecordingIterator($values, $events, $name);
                })
                ->until(static fn(mixed $value): bool => $value === 2)
                ->collect()
                ->values(),
        );
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'inner:1:valid:0',
                'inner:1:current:0',
                'inner:1:next:0',
                'inner:1:valid:1',
                'inner:1:current:1',
                'inner:1:next:1',
                'inner:1:valid:2',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
                'inner:2:valid:0',
                'inner:2:current:0',
            ],
            $events,
        );
    }

    public function testDownstreamFilteringOrEmptyExpansionAfterUntilCannotReadAnotherSourceValue(): void
    {
        foreach ([
            static fn(Sequence $sequence): Sequence => $sequence->filter(static fn(): bool => false),
            static fn(Sequence $sequence): Sequence => $sequence->flatMap(static fn(): iterable => []),
        ] as $operation) {
            $events = [];
            $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');
            $sequence = Sequence::from($source)->until(static fn(mixed $value): bool => $value === 2);

            self::assertSame([], $operation($sequence)->collect()->values());
            self::assertSame(
                ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
                $events,
            );
        }
    }

    public function testUntilAfterTakeReadsOnlyTheTakenValuesAndTakeZeroReadsNothing(): void
    {
        $seen = [];
        $source = static function () use (&$seen): iterable {
            foreach ([1, 2, 3] as $value) {
                $seen[] = $value;
                yield $value;
            }
        };

        self::assertSame(
            [1],
            Sequence::from($source())
                ->take(1)
                ->until(static function (mixed $value) use (&$seen): bool {
                    $seen[] = 'until:' . $value;

                    return false;
                })
                ->collect()
                ->values(),
        );
        self::assertSame([1, 'until:1'], $seen);

        $events = [];
        $untilCalls = 0;
        self::assertSame(
            [],
            Sequence::from(new SequenceRecordingIterator([1], $events, 'source'))
                ->take(0)
                ->until(static function () use (&$untilCalls): bool {
                    ++$untilCalls;

                    return true;
                })
                ->collect()
                ->values(),
        );
        self::assertSame([], $events);
        self::assertSame(0, $untilCalls);
    }

    public function testSkipUntilAfterTakeReadsOnlyTakenValuesAndTakeZeroReadsNothing(): void
    {
        $seen = [];
        $source = static function () use (&$seen): iterable {
            foreach ([1, 2, 3] as $value) {
                $seen[] = $value;
                yield $value;
            }
        };

        self::assertSame(
            [],
            Sequence::from($source())
                ->take(1)
                ->skipUntil(static function (mixed $value) use (&$seen): bool {
                    $seen[] = 'skipUntil:' . $value;

                    return false;
                })
                ->collect()
                ->values(),
        );
        self::assertSame([1, 'skipUntil:1'], $seen);

        $events = [];
        $skipUntilCalls = 0;
        self::assertSame(
            [],
            Sequence::from(new SequenceRecordingIterator([1], $events, 'source'))
                ->take(0)
                ->skipUntil(static function () use (&$skipUntilCalls): bool {
                    ++$skipUntilCalls;

                    return true;
                })
                ->collect()
                ->values(),
        );
        self::assertSame([], $events);
        self::assertSame(0, $skipUntilCalls);
    }

    public function testTakeAfterFlatMapStopsBeforeEitherInputAdvances(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2], $events, 'outer');
        $inner = new SequenceRecordingIterator([1, 11], $events, 'inner');

        $values = Sequence::from($source)
            ->flatMap(static fn(): iterable => $inner)
            ->take(1)
            ->collect()
            ->values();

        self::assertSame([1], $values);
        self::assertSame(['outer:valid:0', 'outer:current:0', 'inner:valid:0', 'inner:current:0'], $events);
    }

    public function testDropAndTakeCountAcrossFlatMapInputsAndNestedExpansions(): void
    {
        self::assertSame(
            [2],
            Sequence::from([1, 2])
                ->flatMap(static fn(int $value): iterable => [$value, $value + 10])
                ->drop(2)
                ->take(1)
                ->collect()
                ->values(),
        );

        $events = [];
        $lastInner = new SequenceRecordingIterator([2, -2], $events, 'last');
        $values = Sequence::from([1, 2])
            ->flatMap(static fn(int $value): iterable => [$value, $value + 10])
            ->flatMap(static fn(int $value): iterable => $value === 2 ? $lastInner : [$value, -$value])
            ->drop(3)
            ->take(2)
            ->collect()
            ->values();

        self::assertSame([-11, 2], $values);
        self::assertSame(['last:valid:0', 'last:current:0'], $events);
    }

    public function testEmptyExpansionContinuesWithTheNextParentValueAtTheFollowingOperation(): void
    {
        $mapped = [];
        $values = Sequence::from([1, 2])
            ->flatMap(static fn(int $value): iterable => $value === 1 ? [] : [$value])
            ->map(static function (int $value) use (&$mapped): int {
                $mapped[] = $value;

                return $value * 10;
            })
            ->collect()
            ->values();

        self::assertSame([20], $values);
        self::assertSame([2], $mapped);
    }

    public function testFilteringOrEmptyExpansionAfterTakeDoesNotReadAnotherSourceValue(): void
    {
        $operations = [
            static fn(Sequence $sequence): Sequence => $sequence->filter(static fn(): bool => false),
            static fn(Sequence $sequence): Sequence => $sequence->flatMap(static fn(): iterable => []),
        ];
        foreach ($operations as $operation) {
            $events = [];
            $sequence = Sequence::from(new SequenceRecordingIterator([1, 2], $events, 'outer'))->take(1);
            $operation($sequence);

            self::assertSame([], $sequence->collect()->values());
            self::assertSame(['outer:valid:0', 'outer:current:0'], $events);
        }
    }

    public function testMultipleTakeLimitsStopFurtherInputReadsAfterTheOutputLimit(): void
    {
        foreach ([[1, 1], [2, 1]] as [$firstLimit, $secondLimit]) {
            $events = [];
            $source = new SequenceRecordingIterator([1, 2], $events, 'outer');
            $inner = new SequenceRecordingIterator([1, 11], $events, 'inner');

            $values = Sequence::from($source)
                ->take($firstLimit)
                ->flatMap(static fn(): iterable => $inner)
                ->take($secondLimit)
                ->collect()
                ->values();

            self::assertSame([1], $values);
            self::assertSame(['outer:valid:0', 'outer:current:0', 'inner:valid:0', 'inner:current:0'], $events);
        }
    }

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

    public function testTakeZeroAtFirstMiddleAndLastPositionsSkipsInputReadsAndCallbacks(): void
    {
        foreach (['first', 'middle', 'last'] as $position) {
            $events = [];
            $source = new class($events) implements IteratorAggregate {
                public int $calls = 0;

                /** @var list<string> */
                public array $events;

                /** @param list<string> $events */
                public function __construct(array &$events)
                {
                    $this->events = &$events;
                }

                public function getIterator(): Traversable
                {
                    ++$this->calls;

                    return new SequenceRecordingIterator([1], $this->events, 'source');
                }
            };
            $calls = 0;
            $sequence = Sequence::from($source);
            if ($position === 'first') {
                $sequence->take(0);
            }
            $sequence->map(static function (mixed $value) use (&$calls): mixed {
                ++$calls;

                return $value;
            });
            if ($position === 'middle') {
                $sequence->take(0);
            }
            $sequence->flatMap(static function (mixed $value) use (&$calls): iterable {
                ++$calls;

                return [$value];
            });
            if ($position === 'last') {
                $sequence->take(0);
            }

            self::assertSame([], $sequence->collect()->values());
            self::assertSame(1, $source->calls);
            self::assertSame([], $events);
            self::assertSame(0, $calls);
        }
    }

    public function testPipelinePreservesNullFalseAndIterableValues(): void
    {
        $values = Sequence::of(null, false, [1])
            ->map(static fn(mixed $value): mixed => $value)
            ->flatMap(static fn(mixed $value): iterable => [$value])
            ->collect()
            ->values();

        self::assertSame([0 => null, 1 => false, 2 => [1]], $values);
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

    /**
     * @mago-expect lint:loop-does-not-iterate
     */
    public function testFlatMapResolvesNestedAggregatesOnlyWhenTheirOuterValueIsRequested(): void
    {
        $events = [];
        $inner = new class($events) implements IteratorAggregate {
            /** @var list<string> */
            public array $events;

            /** @param list<string> $events */
            public function __construct(array &$events)
            {
                $this->events = &$events;
            }

            public function getIterator(): Traversable
            {
                $this->events[] = 'inner aggregate';

                return new SequenceRecordingIterator([1], $this->events, 'inner');
            }
        };
        $outer = new class($inner, $events) implements IteratorAggregate {
            /** @var list<string> */
            public array $events;

            /**
             * @param list<string> $events
             */
            public function __construct(
                private readonly IteratorAggregate $inner,
                array &$events,
            ) {
                $this->events = &$events;
            }

            public function getIterator(): Traversable
            {
                $this->events[] = 'outer aggregate';

                return $this->inner;
            }
        };
        $sequence = Sequence::of('source')->flatMap(static function () use (&$events, $outer): iterable {
            $events[] = 'mapper';

            return $outer;
        });

        $iterator = $sequence->getIterator();
        self::assertSame([], $events);
        $values = [];
        foreach ($iterator as $value) {
            $values[] = $value;
            break;
        }
        self::assertSame(['mapper', 'outer aggregate', 'inner aggregate', 'inner:valid:0', 'inner:current:0'], $events);
        self::assertSame([1], $values);
    }

    public function testFlatMapReadsUnstartedAndExhaustedGeneratorsFromTheirCurrentStates(): void
    {
        $unstarted = (static function (): Generator {
            yield 'first';
            yield 'second';
        })();
        $exhausted = (static function (): Generator {
            yield 'ignored';
        })();
        iterator_to_array($exhausted);

        $identity = static function (mixed $values): iterable {
            if (!is_iterable($values)) {
                throw new \LogicException('Expected an iterable test value.');
            }

            return $values;
        };

        self::assertSame(
            ['first', 'second'],
            Sequence::from([$unstarted, $exhausted])->flatMap($identity)->collect()->values(),
        );
    }

    public function testFlatMapInputFailuresKeepTheirIdentityAndConsumeTheSequence(): void
    {
        foreach (['getIterator', 'valid', 'current', 'next'] as $failurePoint) {
            $expected = new \RuntimeException($failurePoint . ' failed');
            $inner = new class($failurePoint, $expected) implements Iterator {
                private int $position = 0;

                public function __construct(
                    private readonly string $failurePoint,
                    private readonly \RuntimeException $exception,
                ) {}

                public function current(): int
                {
                    if ($this->failurePoint === 'current') {
                        throw $this->exception;
                    }

                    return 1;
                }

                public function key(): int
                {
                    return $this->position;
                }

                public function next(): void
                {
                    if ($this->failurePoint === 'next') {
                        throw $this->exception;
                    }

                    ++$this->position;
                }

                public function rewind(): void
                {
                    throw new \LogicException('FlatMap rewound an existing iterator.');
                }

                public function valid(): bool
                {
                    if ($this->failurePoint === 'valid') {
                        throw $this->exception;
                    }

                    return $this->position === 0;
                }
            };
            if ($failurePoint === 'getIterator') {
                $inner = new class($expected) implements IteratorAggregate {
                    public function __construct(
                        private readonly \RuntimeException $exception,
                    ) {}

                    public function getIterator(): Traversable
                    {
                        throw $this->exception;
                    }
                };
            }
            $sequence = Sequence::of('outer')->flatMap(static fn(): iterable => $inner);

            try {
                $sequence->collect();
                self::fail($failurePoint . ' exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            try {
                $sequence->getIterator();
                self::fail('The sequence remained reusable after ' . $failurePoint . ' failed.');
            } catch (SequenceConsumedException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

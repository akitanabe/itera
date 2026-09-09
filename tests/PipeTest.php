<?php

declare(strict_types=1);

namespace Itera\Tests;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use Itera\Collection;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

use function Itera\Aggregator\collect as collectWith;
use function Itera\Aggregator\count as countWith;
use function Itera\Pipe\aggregate;
use function Itera\Pipe\associate;
use function Itera\Pipe\collect;
use function Itera\Pipe\drop;
use function Itera\Pipe\filter;
use function Itera\Pipe\flatMap;
use function Itera\Pipe\fold;
use function Itera\Pipe\getIterator;
use function Itera\Pipe\map;
use function Itera\Pipe\scan;
use function Itera\Pipe\sequence;
use function Itera\Pipe\skipUntil;
use function Itera\Pipe\take;
use function Itera\Pipe\tap;
use function Itera\Pipe\until;

/** @mago-expect lint:too-many-methods */
final class PipeTest extends TestCase
{
    public function testPipeTransformsAnIterableIntoACollectionThroughProductionAutoload(): void
    {
        $result = self::integers(1, 2)
            |> sequence()
            |> map(static fn(int $value): string => "value:{$value}")
            |> collect();

        self::assertInstanceOf(Collection::class, $result);
        self::assertSame(['value:1', 'value:2'], $result->values());
    }

    public function testEveryFactoryReturnsAClosureWithOneRequiredInput(): void
    {
        $factories = [
            sequence(),
            map(static fn(int $value): int => $value),
            scan(0, static fn(int $state, int $value): int => $state + $value),
            tap(static function (int $value): void {}),
            filter(static fn(int $value): bool => $value > 0),
            flatMap(static fn(int $value): iterable => [$value]),
            until(static fn(int $value): bool => $value > 0),
            skipUntil(static fn(int $value): bool => $value > 0),
            take(1),
            drop(1),
            aggregate(countWith()),
            associate(static fn(int $value): string => (string) $value),
            collect(),
            fold(0, static fn(int $state, int $value): int => $state + $value),
            getIterator(),
        ];

        foreach ($factories as $factory) {
            self::assertInstanceOf(Closure::class, $factory);
            self::assertSame(1, new ReflectionFunction($factory)->getNumberOfRequiredParameters());
        }
    }

    public function testIntermediateAdaptersMutateTheSameSequenceWithoutRunningSourceOrCallbacks(): void
    {
        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'source';
            yield 1;
            yield 2;
        };
        $sequence = Sequence::from($source());
        $mapper = map(static function (int $value) use (&$events): int {
            self::assertSame(1, func_num_args());
            $events[] = "map:{$value}";

            return $value * 10;
        });

        $actual = $sequence
            |> $mapper
            |> filter(static function (int $value) use (&$events): bool {
                self::assertSame(1, func_num_args());
                $events[] = "filter:{$value}";

                return $value === 20;
            });

        self::assertSame($sequence, $actual);
        self::assertSame([], $events);
        self::assertSame([20], ($actual |> collect())->values());
        self::assertSame(['source', 'map:1', 'filter:10', 'map:2', 'filter:20'], $events);

        self::assertSame([30], (Sequence::from(self::integers(3)) |> $mapper |> collect())->values());
    }

    public function testSavedScanFactoryKeepsStateIndependentForEachSequence(): void
    {
        $events = [];
        $runningTotal = scan(0, static function (int $state, int $value) use (&$events): int {
            $events[] = $value;

            return $state + $value;
        });

        self::assertSame([], $events);
        self::assertSame([1, 3], (Sequence::from([1, 2]) |> $runningTotal |> collect())->values());
        self::assertSame([10, 30], (Sequence::from([10, 20]) |> $runningTotal |> collect())->values());
        self::assertSame([1, 2, 10, 20], $events);
    }

    public function testScanPipeDefersWorkUntilConsumptionAndReturnsTheSameSequence(): void
    {
        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'source';
            yield 1;
        };
        $scan = scan(0, static function (int $state, int $value) use (&$events): int {
            $events[] = 'step';

            return $state + $value;
        });
        $sequence = Sequence::from($source());

        $actual = $sequence |> $scan;

        self::assertSame($sequence, $actual);
        self::assertSame([], $events);
        self::assertSame([1], ($actual |> collect())->values());
        self::assertSame(['source', 'step'], $events);
    }

    public function testScanPipeRejectsConsumedInputAndPreservesCallbackExceptionIdentity(): void
    {
        $scan = scan(0, static fn(int $state, int $value): int => $state + $value);
        $consumed = Sequence::from([1]);
        $consumed->collect();

        $this->expectExceptionFrom(static fn(): Sequence => $consumed |> $scan, SequenceConsumedException::class);

        $expected = new \RuntimeException('scan failed');
        $fails = scan(0, static function (int $state, int $value) use ($expected): int {
            throw $expected;
        });
        $sequence = Sequence::from([1]);

        try {
            $sequence |> $fails |> collect();
            self::fail('The scan exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    public function testTapPipeDefersEffectsAndCanBeReusedAcrossSequences(): void
    {
        $events = [];
        $observe = tap(static function (int $value) use (&$events): void {
            $events[] = $value;
        });
        /** @var iterable<int> $firstValues */
        $firstValues = [1, 2];
        $firstInput = $firstValues |> sequence();
        $first = $firstInput |> $observe;
        /** @var iterable<int> $secondValues */
        $secondValues = [3];
        $second = $secondValues |> sequence() |> $observe;

        self::assertSame([], $events);
        self::assertSame($firstInput, $first);
        self::assertSame([1, 2], ($first |> collect())->values());
        self::assertSame([3], ($second |> collect())->values());
        self::assertSame([1, 2, 3], $events);
    }

    public function testTapPipeRejectsConsumedInputAndPreservesCallbackExceptionIdentity(): void
    {
        $observe = tap(static function (int $value): void {});
        $consumed = Sequence::from([1]);
        $consumed->collect();

        $this->expectExceptionFrom(static fn(): Sequence => $consumed |> $observe, SequenceConsumedException::class);

        $expected = new \RuntimeException('tap failed');
        $fails = tap(static function (int $value) use ($expected): void {
            throw $expected;
        });
        $sequence = Sequence::from([1]);

        try {
            $sequence |> $fails |> collect();
            self::fail('The tap exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    public function testPipelinePreservesExpansionAndBoundaryOrder(): void
    {
        $untilCalls = [];
        $skipCalls = [];

        $actual = self::integers(1, 2, 3, 4)
            |> sequence()
            |> flatMap(static fn(int $value): iterable => [$value, $value * 10])
            |> drop(1)
            |> until(static function (int $value) use (&$untilCalls): bool {
                $untilCalls[] = $value;

                return $value === 3;
            })
            |> skipUntil(static function (int $value) use (&$skipCalls): bool {
                $skipCalls[] = $value;

                return $value === 2;
            })
            |> take(3)
            |> collect();

        self::assertSame([2, 20, 3], $actual->values());
        self::assertSame([10, 2, 20, 3], $untilCalls);
        self::assertSame([10, 2], $skipCalls);
    }

    public function testRemainingIntermediateAdaptersReturnTheSameSequenceWithoutExecutingWork(): void
    {
        $events = [];
        $source = static function (int $value) use (&$events): iterable {
            $events[] = "source:{$value}";
            yield $value;
        };

        $flatMapped = Sequence::from($source(1));
        $actual = $flatMapped
            |> flatMap(static function (int $value) use (&$events): iterable {
                $events[] = "flatMap:{$value}";

                yield $value;
            });
        self::assertSame($flatMapped, $actual);

        $until = Sequence::from($source(2));
        $actual = $until
            |> until(static function (int $value) use (&$events): bool {
                $events[] = "until:{$value}";

                return false;
            });
        self::assertSame($until, $actual);

        $skipUntil = Sequence::from($source(3));
        $actual = $skipUntil
            |> skipUntil(static function (int $value) use (&$events): bool {
                $events[] = "skipUntil:{$value}";

                return true;
            });
        self::assertSame($skipUntil, $actual);

        $taken = Sequence::from($source(4));
        self::assertSame($taken, $taken |> take(1));

        $dropped = Sequence::from($source(5));
        self::assertSame($dropped, $dropped |> drop(1));

        self::assertSame([], $events);
    }

    public function testUntilAndSkipUntilHandleAnUnmatchedFiniteInput(): void
    {
        $untilCalls = [];
        $untilResult = self::integers(1, 2, 3)
            |> sequence()
            |> until(static function (int $value) use (&$untilCalls): bool {
                $untilCalls[] = $value;

                return false;
            })
            |> collect();

        $skipCalls = [];
        $skipResult = self::integers(1, 2, 3)
            |> sequence()
            |> skipUntil(static function (int $value) use (&$skipCalls): bool {
                $skipCalls[] = $value;

                return false;
            })
            |> collect();

        self::assertSame([1, 2, 3], $untilResult->values());
        self::assertSame([1, 2, 3], $untilCalls);
        self::assertSame([], $skipResult->values());
        self::assertSame([1, 2, 3], $skipCalls);
    }

    public function testSequenceAcceptsSupportedIterableStatesAndKeepsExistingSequenceIdentity(): void
    {
        $iterator = new ArrayIterator([1, 2, 3]);
        $iterator->next();
        $existing = Sequence::of('kept');

        self::assertSame([1, 2], ([1, 2] |> sequence() |> collect())->values());
        self::assertSame(['a', 'b'], (Collection::of('a', 'b') |> sequence() |> collect())->values());
        self::assertSame([2, 3], ($iterator |> sequence() |> collect())->values());
        self::assertSame($existing, $existing |> sequence());
    }

    public function testTakeZeroDoesNotReadAValueOrRunAnEarlierCallback(): void
    {
        $events = [];
        $source = static function (int $value) use (&$events): iterable {
            $events[] = 'started';
            yield $value;
        };

        $result = $source(1)
            |> sequence()
            |> map(static function (int $value) use (&$events): int {
                $events[] = "mapped:{$value}";

                return $value;
            })
            |> take(0)
            |> collect();

        self::assertSame([], $result->values());
        self::assertSame([], $events);
    }

    public function testUntilStopsWithoutReadingAnotherSourceValue(): void
    {
        $read = [];
        $result = self::recordingIntegers($read, 1, 2, 3)
            |> sequence()
            |> until(static fn(int $value): bool => $value === 2)
            |> collect();

        self::assertSame([1, 2], $result->values());
        self::assertSame([1, 2], $read);
    }

    public function testSavedCountFactoriesKeepNoStateBetweenSequences(): void
    {
        $takeTwo = take(2);
        $dropOne = drop(1);

        self::assertSame(
            [2, 3],
            ([1, 2, 3]
                |> sequence()
                |> $dropOne
                |> $takeTwo
                |> collect())->values(),
        );
        self::assertSame(
            ['b', 'c'],
            (['a', 'b', 'c']
                |> sequence()
                |> $dropOne
                |> $takeTwo
                |> collect())->values(),
        );
    }

    public function testTerminalAdaptersPreserveTheirSequenceContracts(): void
    {
        $first = Sequence::empty() |> collect();
        $second = Sequence::empty() |> collect();

        self::assertNotSame($first, $second);
        self::assertSame([], $first->values());
        self::assertSame(
            'initial',
            Sequence::empty() |> fold('initial', static fn(string $state, mixed $value): string => $state),
        );
        self::assertSame(
            'start:1:2',
            Sequence::from(self::integers(1, 2))
                |> fold('start', static function (string $state, int $value): string {
                    self::assertSame(2, func_num_args());

                    return "{$state}:{$value}";
                }),
        );

        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'read';
            yield 'value';
        };
        $sequence = Sequence::from($source());
        $iterator = $sequence |> getIterator();

        self::assertSame([], $events);
        $this->assertConsumed($sequence);
        self::assertSame(['value'], iterator_to_array($iterator));
        self::assertSame(['read'], $events);
    }

    public function testAggregateFactoryIsLazyAndReusableAcrossSequences(): void
    {
        $events = [];
        $predicateCalls = 0;
        $source = static function () use (&$events): iterable {
            $events[] = 'source';
            yield 1;
            yield 2;
        };
        $definition = collectWith();
        $materialize = aggregate($definition);
        $hasValue = aggregate(\Itera\Aggregator\any(static function () use (&$predicateCalls): bool {
            $predicateCalls++;

            return true;
        }));

        self::assertSame([], $events);
        self::assertSame(0, $predicateCalls);
        self::assertSame([1, 2], (Sequence::from($source()) |> $materialize)->values());
        self::assertSame(['source'], $events);
        self::assertSame(['other'], (Sequence::of('other') |> $materialize)->values());
        self::assertTrue(Sequence::of('first') |> $hasValue);
        self::assertTrue(Sequence::of('second') |> $hasValue);
        self::assertSame(2, $predicateCalls);
    }

    public function testAssociateSelectsKeysWhenAppliedAndKeepsTheLastDuplicate(): void
    {
        $calls = [];
        $byLength = associate(static function (string $value) use (&$calls): int {
            $calls[] = $value;

            return strlen($value);
        });

        self::assertSame([], $calls);
        self::assertSame(
            [3 => 'two', 5 => 'three'],
            (Sequence::from(self::strings('one', 'two', 'three')) |> $byLength)->raw(),
        );
        self::assertSame(['one', 'two', 'three'], $calls);
        self::assertSame([1 => 'a'], (Sequence::from(self::strings('a')) |> $byLength)->raw());

        $consumed = Sequence::from(self::strings('used'));
        $consumed->collect();
        $this->expectExceptionFrom(static fn(): \Itera\Map => $consumed |> $byLength, SequenceConsumedException::class);
    }

    public function testAggregateRejectsConsumedInputAndPreservesCallbackExceptionIdentity(): void
    {
        $count = aggregate(countWith());
        $consumed = Sequence::of(1);
        $consumed->collect();
        $this->expectExceptionFrom(static fn(): int => $consumed |> $count, SequenceConsumedException::class);

        $expected = new \RuntimeException('aggregate callback failed');
        $fails = aggregate(\Itera\Aggregator\any(static function () use ($expected): never {
            throw $expected;
        }));
        $sequence = Sequence::of(1);

        try {
            $sequence |> $fails;
            self::fail('The callback exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    public function testValidationAndFailuresOccurAtApplicationOrConsumption(): void
    {
        $negativeTake = take(-1);
        $negativeDrop = drop(-1);

        $this->expectExceptionFrom(
            static fn(): Sequence => Sequence::of(1) |> $negativeTake,
            InvalidArgumentException::class,
        );
        $this->expectExceptionFrom(
            static fn(): Sequence => Sequence::of(1) |> $negativeDrop,
            InvalidArgumentException::class,
        );

        $consumed = Sequence::of(1);
        $consumed->collect();
        $this->expectExceptionFrom(
            static fn(): Sequence => $consumed |> $negativeTake,
            SequenceConsumedException::class,
        );

        $expected = new \RuntimeException('callback failed');
        $sequence = Sequence::of(1)
            |> map(static function () use ($expected): never {
                throw $expected;
            });

        try {
            $sequence |> collect();
            self::fail('The callback exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    /**
     * @param Closure(): mixed $operation
     * @param class-string<\Throwable> $expectedClass
     */
    private function expectExceptionFrom(Closure $operation, string $expectedClass): void
    {
        try {
            $operation();
        } catch (\Throwable $actual) {
            self::assertInstanceOf($expectedClass, $actual);

            return;
        }

        self::fail("Expected {$expectedClass} was not thrown.");
    }

    /**
     * @template T
     * @param Sequence<T> $sequence
     */
    private function assertConsumed(Sequence $sequence): void
    {
        try {
            $sequence->getIterator();
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);

            return;
        }

        self::fail('The sequence remained reusable.');
    }

    /** @return iterable<int> */
    private static function integers(int ...$values): iterable
    {
        yield from $values;
    }

    /** @return iterable<string> */
    private static function strings(string ...$values): iterable
    {
        yield from $values;
    }

    /**
     * @param list<int> $read
     * @return iterable<int>
     */
    private static function recordingIntegers(array &$read, int ...$values): iterable
    {
        foreach ($values as $value) {
            $read[] = $value;
            yield $value;
        }
    }
}

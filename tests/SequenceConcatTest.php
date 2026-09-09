<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Traversable;

use function Itera\Pipe\concat as concatPipe;

/** @mago-expect lint:too-many-methods */
final class SequenceConcatTest extends TestCase
{
    public function testConcatEmitsValuesInInputOrderWithContinuousOutputKeys(): void
    {
        $sequence = Sequence::concat(Sequence::from(['first' => 'a', 7 => 'b']), Sequence::from(['second' => 'c']));

        self::assertSame([0 => 'a', 1 => 'b', 2 => 'c'], iterator_to_array($sequence));
    }

    public function testConcatSupportsOneInputAsAFreshSequence(): void
    {
        $input = Sequence::from([1]);
        $concat = Sequence::concat($input);

        self::assertNotSame($input, $concat);
        self::assertSame(
            [10],
            $concat
                ->map(static fn(int $value): int => $value * 10)
                ->collect()
                ->values(),
        );
    }

    public function testConcatHandlesEmptyInputsWithoutChangingTheRemainingOrder(): void
    {
        self::assertSame(
            [1, 2],
            Sequence::concat(Sequence::empty(), Sequence::of(1), Sequence::empty(), Sequence::of(2))
                ->collect()
                ->values(),
        );
        self::assertSame([], Sequence::concat(Sequence::empty(), Sequence::empty())->collect()->values());
    }

    public function testConcatDoesNotStartInputsUntilConsumedAndTransitionsSequentially(): void
    {
        $events = [];
        $first = Sequence::from($this->source($events, 'first', [1, 2]));
        $second = Sequence::from($this->source($events, 'second', [3]));

        $concat = Sequence::concat($first, $second);

        self::assertSame([], $events);
        $iterator = $concat->getIterator();
        self::assertSame([], $events);

        self::assertSame([1, 2, 3], iterator_to_array($iterator));
        self::assertSame(
            [
                'first:start',
                'first:read:1',
                'first:read:2',
                'first:end',
                'second:start',
                'second:read:3',
                'second:end',
            ],
            $events,
        );
        $this->assertConsumed($concat);
        $this->assertConsumed($first);
        $this->assertConsumed($second);
    }

    public function testAGetIteratorOnlyConsumesConcatWhileInputsRemainAvailable(): void
    {
        $first = Sequence::of(1);
        $second = Sequence::of(2);
        $concat = Sequence::concat($first, $second);

        $concat->getIterator();

        $this->assertConsumed($concat);
        self::assertSame([1], $first->collect()->values());
        self::assertSame([2], $second->collect()->values());
    }

    public function testTakeZeroConsumesOnlyConcatAndLeavesEveryInputAvailable(): void
    {
        $first = Sequence::of(1);
        $second = Sequence::of(2);
        $concat = Sequence::concat($first, $second);

        self::assertSame([], $concat->take(0)->collect()->values());
        $this->assertConsumed($concat);
        self::assertSame([1], $first->collect()->values());
        self::assertSame([2], $second->collect()->values());
    }

    public function testStoppingAtAnInputBoundaryDoesNotStartTheNextInput(): void
    {
        $events = [];
        $firstSource = new SequenceRecordingIterator([1, 2], $events, 'first');
        $secondSource = new SequenceRecordingIterator([3], $events, 'second');
        $first = Sequence::from($firstSource);
        $second = Sequence::from($secondSource);

        $concat = Sequence::concat($first, $second)->take(2);

        self::assertSame([1, 2], $concat->collect()->values());
        $this->assertConsumed($concat);
        self::assertSame(
            ['first:valid:0', 'first:current:0', 'first:next:0', 'first:valid:1', 'first:current:1'],
            $events,
        );
        $this->assertConsumed($first);
        self::assertSame([3], $second->collect()->values());
    }

    public function testTakeCrossesAnInputBoundaryWithoutReadingPastItsLimit(): void
    {
        $events = [];
        $first = Sequence::from($this->source($events, 'first', [1, 2]));
        $second = Sequence::from($this->source($events, 'second', [3, 4]));
        $third = Sequence::from($this->source($events, 'third', [5]));
        $concat = Sequence::concat($first, $second, $third)->take(3);

        self::assertSame([1, 2, 3], $concat->collect()->values());
        self::assertSame(
            [
                'first:start',
                'first:read:1',
                'first:read:2',
                'first:end',
                'second:start',
                'second:read:3',
            ],
            $events,
        );
        $this->assertConsumed($concat);
        $this->assertConsumed($first);
        $this->assertConsumed($second);
        self::assertSame([5], $third->collect()->values());
        self::assertSame(
            [
                'first:start',
                'first:read:1',
                'first:read:2',
                'first:end',
                'second:start',
                'second:read:3',
                'third:start',
                'third:read:5',
                'third:end',
            ],
            $events,
        );
    }

    public function testInputUntilFinishesThatInputBeforeTheNextInputStarts(): void
    {
        $events = [];
        $firstSource = new SequenceRecordingIterator([1, 2, 3], $events, 'first');
        $secondSource = new SequenceRecordingIterator([4], $events, 'second');

        $first = Sequence::from($firstSource)->until(static fn(mixed $value): bool => $value === 2);
        $second = Sequence::from($secondSource);
        $concat = Sequence::concat($first, $second);

        self::assertSame([1, 2, 4], $concat->collect()->values());
        $this->assertConsumed($concat);
        $this->assertConsumed($first);
        $this->assertConsumed($second);
        self::assertSame(
            [
                'first:valid:0',
                'first:current:0',
                'first:next:0',
                'first:valid:1',
                'first:current:1',
                'second:valid:0',
                'second:current:0',
                'second:next:0',
                'second:valid:1',
            ],
            $events,
        );
    }

    public function testStoppingOnTheLastValueOfAnInputDoesNotStartTheNextInput(): void
    {
        $events = [];
        $firstSource = new SequenceRecordingIterator([1, 2], $events, 'first');
        $secondSource = new SequenceRecordingIterator([3], $events, 'second');

        $first = Sequence::from($firstSource);
        $second = Sequence::from($secondSource);
        $concat = Sequence::concat($first, $second)->until(static fn(mixed $value): bool => $value === 2);

        self::assertSame([1, 2], $concat->collect()->values());
        $this->assertConsumed($concat);
        self::assertSame(
            ['first:valid:0', 'first:current:0', 'first:next:0', 'first:valid:1', 'first:current:1'],
            $events,
        );
        $this->assertConsumed($first);
        self::assertSame([3], $second->collect()->values());
    }

    public function testAnUnreachedInputCanBeConsumedAfterDownstreamEarlyTermination(): void
    {
        $first = Sequence::of(1, 2);
        $second = Sequence::of(3, 4);
        $concat = Sequence::concat($first, $second)->take(1);

        self::assertSame([1], $concat->collect()->values());
        $this->assertConsumed($concat);
        self::assertSame([3, 4], $second->collect()->values());
        $this->assertConsumed($first);
    }

    public function testAConsumerBreakLeavesUnreachedInputsAvailable(): void
    {
        $first = Sequence::of(1, 2);
        $second = Sequence::of(3);
        $concat = Sequence::concat($first, $second);

        $stopped = false;
        foreach ($concat as $value) {
            if ($stopped) {
                break;
            }

            self::assertSame(1, $value);
            $stopped = true;
        }

        $this->assertConsumed($concat);
        $this->assertConsumed($first);
        self::assertSame([3], $second->collect()->values());
    }

    public function testInputOperationsAndConcatOperationsKeepTheirBoundaries(): void
    {
        $insideChunks = Sequence::concat(Sequence::of(1, 2, 3)->chunk(2), Sequence::of(4)->chunk(2))
            ->collect()
            ->values();
        self::assertSame(
            [[1, 2], [3], [4]],
            array_map(static fn(\Itera\Collection $chunk): array => $chunk->values(), $insideChunks),
        );

        $acrossChunks = Sequence::concat(Sequence::of(1, 2), Sequence::of(3, 4))->chunk(3)->collect()->values();
        self::assertSame(
            [[1, 2, 3], [4]],
            array_map(static fn(\Itera\Collection $chunk): array => $chunk->values(), $acrossChunks),
        );

        self::assertSame(
            [10, 20, 30],
            Sequence::concat(Sequence::from([1, 2]), Sequence::from([3]))
                ->map(static fn(int $value): int => $value * 10)
                ->collect()
                ->values(),
        );

        self::assertSame(
            [1, 11, 2, 12],
            Sequence::concat(
                Sequence::from([1])->flatMap(static fn(int $value): iterable => [$value, $value + 10]),
                Sequence::from([2])->flatMap(static fn(int $value): iterable => [$value, $value + 10]),
            )
                ->collect()
                ->values(),
        );
        self::assertSame(
            [1, 10, 2, 20],
            Sequence::concat(Sequence::from([1, 2]))
                ->flatMap(static fn(int $value): iterable => [$value, $value * 10])
                ->collect()
                ->values(),
        );
        self::assertSame(
            [11, 21, 301, 401],
            Sequence::concat(
                Sequence::from([1, 2])->map(static fn(int $value): int => $value * 10),
                Sequence::from([3, 4])->map(static fn(int $value): int => $value * 100),
            )
                ->map(static fn(int $value): int => $value + 1)
                ->collect()
                ->values(),
        );
    }

    public function testTakeAndFlatMapOrderIsPreservedInsideInputsAndAfterConcat(): void
    {
        self::assertSame(
            [1, 11, 2],
            Sequence::concat(Sequence::from([1, 2])->take(1)->flatMap(static fn(int $value): iterable => [
                $value,
                $value + 10,
            ]), Sequence::from([2]))
                ->collect()
                ->values(),
        );
        self::assertSame(
            [1, 2],
            Sequence::concat(
                Sequence::from([1, 2])->flatMap(static fn(int $value): iterable => [$value, $value + 10])->take(1),
                Sequence::from([2]),
            )
                ->collect()
                ->values(),
        );

        $takeThenFlatMapEvents = [];
        $takeThenFlatMapFollowing = Sequence::from($this->source($takeThenFlatMapEvents, 'following', [3]));
        $takeThenFlatMap = Sequence::concat(Sequence::from([1, 2]), $takeThenFlatMapFollowing)
            ->take(1)
            ->flatMap(static fn(int $value): iterable => [$value, $value + 10]);

        self::assertSame([1, 11], $takeThenFlatMap->collect()->values());
        $this->assertConsumed($takeThenFlatMap);
        self::assertSame([], $takeThenFlatMapEvents);
        self::assertSame([3], $takeThenFlatMapFollowing->collect()->values());

        $flatMapThenTakeEvents = [];
        $flatMapThenTakeFollowing = Sequence::from($this->source($flatMapThenTakeEvents, 'following', [3]));
        $flatMapThenTake = Sequence::concat(Sequence::from([1, 2]), $flatMapThenTakeFollowing)
            ->flatMap(static fn(int $value): iterable => [$value, $value + 10])
            ->take(1);

        self::assertSame([1], $flatMapThenTake->collect()->values());
        $this->assertConsumed($flatMapThenTake);
        self::assertSame([], $flatMapThenTakeEvents);
        self::assertSame([3], $flatMapThenTakeFollowing->collect()->values());
    }

    public function testAnInfiniteInputCanBeBoundedWithoutStartingTheFollowingInput(): void
    {
        $events = [];
        $infinite = Sequence::from($this->infiniteSource($events));
        $following = Sequence::from($this->source($events, 'following', [3]));

        $concat = Sequence::concat($infinite, $following)->take(2);

        self::assertSame([1, 2], $concat->collect()->values());
        $this->assertConsumed($concat);
        self::assertSame(['infinite:1', 'infinite:2'], $events);
        self::assertSame([3], $following->collect()->values());
    }

    public function testInputChangesMadeAfterConstructionAreObservedAtItsTurn(): void
    {
        $input = Sequence::from([1]);
        $concat = Sequence::concat($input);
        $input->map(static fn(int $value): int => $value + 1);

        self::assertSame([2], $concat->collect()->values());
    }

    public function testDownstreamOperationsStayOnTheFreshSingleInputSequence(): void
    {
        $input = Sequence::from([1]);
        $concat = Sequence::concat($input)->map(static fn(int $value): int => $value * 10);

        self::assertSame([1], $input->collect()->values());

        try {
            $concat->collect();
            self::fail('The independently consumed input was not rejected.');
        } catch (SequenceConsumedException $exception) {
            self::assertInstanceOf(SequenceConsumedException::class, $exception);
        }

        $this->assertConsumed($concat);
    }

    public function testAlreadyConsumedInputIsRejectedOnlyWhenReached(): void
    {
        $leading = Sequence::from([1]);
        $consumed = Sequence::from([2]);
        $consumed->collect();
        $unreached = Sequence::from([3]);
        $concat = Sequence::concat($leading, $consumed, $unreached);
        $seen = [];

        try {
            foreach ($concat as $value) {
                $seen[] = $value;
            }

            self::fail('The consumed input was not rejected.');
        } catch (SequenceConsumedException $exception) {
            self::assertInstanceOf(SequenceConsumedException::class, $exception);
        }

        self::assertSame([1], $seen);
        $this->assertConsumed($concat);
        $this->assertConsumed($leading);
        self::assertSame([3], $unreached->collect()->values());
    }

    public function testInputExceptionsKeepIdentityAndLeaveFollowingInputAvailable(): void
    {
        $expected = new \RuntimeException('concat input failed');
        $failed = Sequence::from($this->throwingSource($expected));
        $following = Sequence::from([2]);

        $concat = Sequence::concat($failed, $following);

        try {
            $concat->collect();
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($concat);
        self::assertSame([2], $following->collect()->values());
        $this->assertConsumed($failed);
    }

    public function testIteratorResolutionExceptionsKeepIdentityAndLeaveFollowingInputAvailable(): void
    {
        $expected = new \RuntimeException('iterator resolution failed');
        $failedSource = new class($expected) implements IteratorAggregate {
            public function __construct(
                private readonly \RuntimeException $exception,
            ) {}

            public function getIterator(): Traversable
            {
                throw $this->exception;
            }
        };
        $failed = Sequence::from($failedSource);
        $following = Sequence::of(2);

        $concat = Sequence::concat($failed, $following);

        try {
            $concat->collect();
            self::fail('The iterator resolution exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($concat);
        self::assertSame([2], $following->collect()->values());
        $this->assertConsumed($failed);
    }

    public function testPipeConcatDirectlyReturnsASequence(): void
    {
        $result = concatPipe(Sequence::of('a'), Sequence::of('b'));

        self::assertInstanceOf(Sequence::class, $result);
        self::assertSame(['a', 'b'], $result->collect()->values());
    }

    /**
     * @param list<string> $events
     * @param list<int> $values
     * @return iterable<int, int>
     */
    private function source(array &$events, string $name, array $values): iterable
    {
        $events[] = $name . ':start';
        foreach ($values as $value) {
            $events[] = $name . ':read:' . $value;
            yield $value;
        }
        $events[] = $name . ':end';
    }

    /**
     * @param list<string> $events
     * @return iterable<int>
     */
    private function infiniteSource(array &$events): iterable
    {
        $value = 1;
        while (true) {
            if ($value === 3) {
                throw new \RuntimeException('Infinite source read past the test bound.');
            }

            $events[] = 'infinite:' . $value;
            yield $value++;
        }
    }

    /** @return iterable<int, int> */
    private function throwingSource(\RuntimeException $exception): iterable
    {
        yield 1;
        throw $exception;
    }

    /**
     * @template T
     * @param Sequence<T> $sequence
     */
    private function assertConsumed(Sequence $sequence): void
    {
        try {
            $sequence->getIterator();
            self::fail('The sequence was reusable.');
        } catch (SequenceConsumedException $exception) {
            self::assertInstanceOf(SequenceConsumedException::class, $exception);
        }
    }
}

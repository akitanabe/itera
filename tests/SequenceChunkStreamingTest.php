<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;
use WeakReference;

/** @mago-expect lint:too-many-methods */
final class SequenceChunkStreamingTest extends TestCase
{
    public function testTakeBeforeChunkEmitsTheFinalPartialChunk(): void
    {
        self::assertSame([[1, 2], [3]], self::values(Sequence::from([1, 2, 3, 4])->take(3)->chunk(2)));
    }

    public function testUntilBeforeChunkIncludesTheMatchInThePartialChunkWithoutAdvancingTheSource(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3, 4], $events, 'source');

        self::assertSame(
            [[1, 2], [3]],
            self::values(
                Sequence::from($source)
                    ->until(static fn(mixed $value): bool => $value === 3)
                    ->chunk(2),
            ),
        );
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
                'source:next:1',
                'source:valid:2',
                'source:current:2',
            ],
            $events,
        );
    }

    public function testUntilAfterChunkDoesNotReadTheNextSourceValue(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame(
            [[1, 2]],
            self::values(
                Sequence::from($source)
                    ->chunk(2)
                    ->until(static fn(Collection $chunk): bool => $chunk->values() === [1, 2]),
            ),
        );
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testTakeAfterChunkDoesNotReadTheNextSourceValue(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame([[1, 2]], self::values(Sequence::from($source)->chunk(2)->take(1)));
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testChunkComposesAcrossFlatMapChildrenIncludingEmptyChildren(): void
    {
        $sequence = Sequence::from([1, 2, 3])->flatMap(static fn(int $value): iterable => (
            $value === 2 ? [] : [$value, $value + 10]
        ))->chunk(3);

        self::assertSame([[1, 11, 3], [13]], self::values($sequence));
    }

    public function testTakeAfterChunkFullyExpandsTheSelectedChunkWithoutReadingTheNextSourceValue(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');
        $inner = new SequenceRecordingIterator([1, 2], $events, 'inner');
        $expanded = Sequence::from($source)
            ->chunk(2)
            ->take(1)
            ->flatMap(static fn(Collection $chunk): iterable => $inner);
        self::assertSame([1, 2], $expanded->collect()->values());
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
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

    public function testUntilAfterChunkExpansionStopsInsideTheSelectedExpansion(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');
        $inner = new SequenceRecordingIterator([1, 2, 9], $events, 'inner');

        $bounded = Sequence::from($source)
            ->chunk(2)
            ->flatMap(static fn(Collection $chunk): iterable => $inner)
            ->until(static fn(mixed $value): bool => $value === 2);
        self::assertSame([1, 2], $bounded->collect()->values());
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
                'inner:valid:0',
                'inner:current:0',
                'inner:next:0',
                'inner:valid:1',
                'inner:current:1',
            ],
            $events,
        );
    }

    public function testMultipleChunkBoundariesPreserveFinalPartialChunks(): void
    {
        $sequence = Sequence::from([1, 2, 3, 4, 5])->chunk(2)->chunk(2);
        $outer = $sequence->collect()->values();

        self::assertCount(2, $outer);
        self::assertSame(
            [[1, 2], [3, 4]],
            array_map(static fn(Collection $chunk): array => $chunk->values(), $outer[0]->values()),
        );
        self::assertSame(
            [[5]],
            array_map(static fn(Collection $chunk): array => $chunk->values(), $outer[1]->values()),
        );
    }

    public function testAdvancingToTheTailReleasesThePreviousFullChunk(): void
    {
        $iterator = new \IteratorIterator(Sequence::from([1, 2, 3])->chunk(2)->getIterator());
        $iterator->rewind();
        $fullChunk = $iterator->current();
        self::assertInstanceOf(Collection::class, $fullChunk);
        $previous = WeakReference::create($fullChunk);
        unset($fullChunk);

        $iterator->next();

        $tail = $iterator->current();
        self::assertInstanceOf(Collection::class, $tail);
        self::assertSame([3], $tail->values());
        self::assertNull($previous->get());
    }

    public function testSourceExceptionDuringAPartialChunkPropagatesWithoutDownstreamOutput(): void
    {
        $expected = new \RuntimeException('failed');
        $events = [];
        $source = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $sequence = Sequence::from($source())->chunk(2)->tap(static function () use (&$events): void {
            $events[] = 'chunk';
        });

        try {
            $sequence->collect();
            self::fail('Source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame([], $events);
        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testCallbackExceptionDuringAPartialChunkProducesNoTailOrLaterEffect(): void
    {
        foreach (['scan', 'tap'] as $operation) {
            $expected = new \RuntimeException("{$operation} failed");
            $downstream = [];
            $sequence = Sequence::from([1, 2]);
            if ($operation === 'scan') {
                $sequence->scan(0, static function (int $state, int $value) use ($expected): int {
                    if ($value === 2) {
                        throw $expected;
                    }

                    return $state + $value;
                });
            }
            if ($operation === 'tap') {
                $sequence->tap(static function (int $value) use ($expected): void {
                    if ($value === 2) {
                        throw $expected;
                    }
                });
            }
            $sequence->chunk(3)->tap(static function () use (&$downstream): void {
                $downstream[] = 'chunk';
            });

            try {
                $sequence->collect();
                self::fail('Callback exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }
            self::assertSame([], $downstream);
        }
    }

    public function testFilteringEveryChunkStillTerminatesAfterNormalUpstreamCompletion(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertSame(
            [],
            Sequence::from($source)
                ->chunk(2)
                ->filter(static fn(): bool => false)
                ->collect()
                ->values(),
        );
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
                'source:next:1',
                'source:valid:2',
                'source:current:2',
                'source:next:2',
                'source:valid:3',
            ],
            $events,
        );
    }

    public function testConsumerInterruptionLeavesSequenceConsumedWithoutReadingTheNextSourceValue(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');
        $sequence = Sequence::from($source)->chunk(2);

        $iterator = new \IteratorIterator($sequence->getIterator());
        $iterator->rewind();
        $chunk = $iterator->current();
        self::assertInstanceOf(Collection::class, $chunk);
        self::assertSame([1, 2], $chunk->values());
        unset($iterator);

        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
        $this->expectException(SequenceConsumedException::class);
        $sequence->getIterator();
    }

    public function testGettingIteratorOnAChunkedSequenceConsumesItImmediately(): void
    {
        $sequence = Sequence::from([1, 2])->chunk(2);

        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $sequence->chunk(1);
    }

    public function testTakeZeroAroundChunkResolvesSourceWithoutReadingOrRunningCallbacks(): void
    {
        foreach ([
            static fn(Sequence $sequence): Sequence => $sequence->take(0)->chunk(2),
            static fn(Sequence $sequence): Sequence => $sequence->chunk(2)->take(0),
        ] as $pipeline) {
            $events = [];
            $source = new class($events) implements \IteratorAggregate {
                /** @var list<string> */
                private array $events;

                /** @param list<string> $events */
                public function __construct(array &$events)
                {
                    $this->events = &$events;
                }

                public function getIterator(): \Traversable
                {
                    $this->events[] = 'resolved';

                    return new SequenceRecordingIterator([1], $this->events, 'source');
                }
            };
            $sequence = $pipeline(Sequence::from($source)->map(static function (mixed $value) use (&$events): mixed {
                $events[] = 'mapped';

                return $value;
            }));

            $iterator = $sequence->getIterator();
            self::assertSame(['resolved'], $events);
            self::assertSame([], iterator_to_array($iterator));
            self::assertSame(['resolved'], $events);
        }
    }

    public function testScanTapAndChunkObserveTheirDeclarationPositions(): void
    {
        $events = [];
        $sequence = Sequence::from([1, 2, 3])
            ->scan(0, static fn(int $state, int $value): int => $state + $value)
            ->tap(static function (int $value) use (&$events): void {
                $events[] = "state:{$value}";
            })
            ->chunk(2)
            ->tap(static function (Collection $chunk) use (&$events): void {
                $events[] = 'chunk:' . implode(',', $chunk->values());
            });

        self::assertSame([[1, 3], [6]], self::values($sequence));
        self::assertSame(['state:1', 'state:3', 'chunk:1,3', 'state:6', 'chunk:6'], $events);
    }

    /**
     * @template T
     * @param Sequence<Collection<T>> $sequence
     * @return list<list<T>>
     */
    private static function values(Sequence $sequence): array
    {
        return array_map(static fn(Collection $chunk): array => $chunk->values(), $sequence->collect()->values());
    }
}

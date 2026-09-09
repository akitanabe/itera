<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\AggregatorExecution;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\combine as combineAggregators;
use function Itera\Aggregator\count as countAggregator;
use function Itera\Pipe\aggregate as aggregatePipe;

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class CustomAggregatorTest extends TestCase
{
    public function testFactoryIsDeferredAndCreatesFreshExecutionsForEachSequence(): void
    {
        $factoryCalls = 0;
        $executionValues = [];
        $definition = Aggregator::custom(static function () use (
            &$factoryCalls,
            &$executionValues,
        ): AggregatorExecution {
            ++$factoryCalls;
            $executionValues[] = [];
            $index = array_key_last($executionValues);
            $events = [];

            return new RecordingCustomExecution($executionValues[$index], $events);
        });

        self::assertSame(0, $factoryCalls);
        self::assertSame([1, 2], Sequence::from([1, 2])->aggregate($definition));
        self::assertSame([3], Sequence::from([3])->aggregate($definition));
        self::assertSame(2, $factoryCalls);
        self::assertSame([[1, 2], [3]], $executionValues);
    }

    public function testEmptyAndTransformedPipelineOutputsAreAggregated(): void
    {
        $definition = Aggregator::custom(static function (): RecordingCustomExecution {
            $values = [];
            $events = [];

            return new RecordingCustomExecution($values, $events);
        });

        self::assertSame([], Sequence::empty()->aggregate($definition));
        self::assertSame(
            [2, -2, 4, -4],
            Sequence::from([1, 2])
                ->map(static fn(int $value): int => $value * 2)
                ->flatMap(static fn(int $value): iterable => [$value, -$value])
                ->aggregate($definition),
        );
    }

    public function testInitialCompletionDoesNotReadValuesOrRunPipelineButFinishesOnce(): void
    {
        $sourceEvents = [];
        $callbackCalls = 0;
        $execution = null;
        $definition = Aggregator::custom(static function () use (&$execution): AggregatorExecution {
            $values = [];
            $events = [];
            /** @mago-expect lint:inline-variable-return */
            $execution = new RecordingCustomExecution($values, $events, initialComplete: true);

            return $execution;
        });
        $source = new AggregatorRecordingSource($sourceEvents);
        $sequence = Sequence::from($source)->map(static function (int $value) use (&$callbackCalls): int {
            ++$callbackCalls;

            return $value;
        });

        self::assertSame([], $sequence->aggregate($definition));

        self::assertTrue($source->resolved);
        self::assertSame([], $sourceEvents);
        self::assertSame(0, $callbackCalls);
        self::assertNotNull($execution);
        self::assertSame(1, $execution->finishCalls);
        $this->assertSequenceConsumed($sequence);
    }

    public function testEarlyCompletionStopsBeforeReadingTheNextValue(): void
    {
        /** @var list<string> $events */
        $events = [];
        $values = [];
        $execution = new RecordingCustomExecution($values, $events, completeAfter: 2);
        $definition = Aggregator::custom(static fn() => $execution);

        self::assertSame(
            [1, 2],
            Sequence::from(new CustomIntRecordingIterator([1, 2, 3], $events, 'source'))->aggregate($definition),
        );
        self::assertSame(
            [
                'source:valid:0',
                'source:current:0',
                'advance:1',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
                'advance:2',
            ],
            $events,
        );
    }

    public function testCustomExecutionCanBeUsedThroughPipeAndFlatNamedCombine(): void
    {
        $sum = Aggregator::custom(static fn() => new SumCustomExecution());
        $sequence = Sequence::from([1, 2]);
        $result = $sequence |> aggregatePipe($sum);

        self::assertSame(3, $result);
        $this->assertSequenceConsumed($sequence);
        self::assertSame(
            ['custom' => 3, 'count' => 2],
            Sequence::from([1, 2])->aggregate(combineAggregators(custom: $sum, count: countAggregator())),
        );

        $factoryCalls = 0;
        $reused = Aggregator::custom(static function () use (&$factoryCalls): SumCustomExecution {
            ++$factoryCalls;

            return new SumCustomExecution();
        });
        self::assertSame(
            ['first' => 3, 'second' => 3],
            Sequence::from([1, 2])->aggregate(combineAggregators(first: $reused, second: $reused)),
        );
        self::assertSame(2, $factoryCalls);
    }

    public function testInitialCompleteCombinedExecutionsDoNotReadSourceAndFinishInOrder(): void
    {
        $sourceEvents = [];
        $executionEvents = [];
        $first = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('first', $executionEvents, initialComplete: true);
        });
        $second = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('second', $executionEvents, initialComplete: true);
        });

        $result = Sequence::from(
            new CustomIntRecordingIterator([1], $sourceEvents, 'source'),
        )->aggregate(combineAggregators(first: $first, second: $second));

        self::assertSame(['first' => 0, 'second' => 0], $result);
        self::assertSame([], $sourceEvents);
        self::assertSame(
            [
                'first:factory',
                'second:factory',
                'first:finish',
                'second:finish',
            ],
            self::withoutCompletionQueries($executionEvents),
        );
    }

    public function testCombinedInitialCompleteChildIsSkippedWhileAnIncompleteChildAdvances(): void
    {
        $executionEvents = [];
        $complete = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('complete', $executionEvents, initialComplete: true);
        });
        $remaining = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('remaining', $executionEvents, completeAfter: 2);
        });

        self::assertSame(
            ['complete' => 0, 'remaining' => 2],
            Sequence::from([1, 2])->aggregate(combineAggregators(complete: $complete, remaining: $remaining)),
        );
        self::assertSame(
            ['complete:factory', 'remaining:factory'],
            array_values(array_filter($executionEvents, static fn(mixed $event): bool => str_ends_with(
                (string) $event,
                ':factory',
            ))),
        );
        self::assertSame(
            ['remaining:advance:1', 'remaining:advance:2'],
            array_values(array_filter($executionEvents, static fn(mixed $event): bool => str_contains(
                (string) $event,
                ':advance:',
            ))),
        );
        self::assertSame(
            ['complete:finish', 'remaining:finish'],
            array_values(array_filter($executionEvents, static fn(mixed $event): bool => str_ends_with(
                (string) $event,
                ':finish',
            ))),
        );
        self::assertNotContains('complete:advance:1', $executionEvents);
        self::assertNotContains('complete:advance:2', $executionEvents);
    }

    public function testCombinedChildrenCompleteAtDifferentValuesWithoutReadingAnExtraValue(): void
    {
        $executionEvents = [];
        $sourceEvents = [];
        $first = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('first', $executionEvents, completeAfter: 1);
        });
        $second = Aggregator::custom(static function () use (&$executionEvents): ScriptedCustomExecution {
            return new ScriptedCustomExecution('second', $executionEvents, completeAfter: 2);
        });

        $result = Sequence::from(
            new CustomIntRecordingIterator([1, 2, 3], $sourceEvents, 'source'),
        )->aggregate(combineAggregators(first: $first, second: $second));

        self::assertSame(['first' => 1, 'second' => 2], $result);
        self::assertSame(
            ['first:advance:1', 'second:advance:1', 'second:advance:2'],
            array_values(array_filter($executionEvents, static fn(string $event): bool => str_contains(
                $event,
                ':advance:',
            ))),
        );
        self::assertSame(
            ['first:finish', 'second:finish'],
            array_values(array_filter($executionEvents, static fn(string $event): bool => str_ends_with(
                $event,
                ':finish',
            ))),
        );
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $sourceEvents,
        );
    }

    public function testCombinedFactoryFailuresStopRemainingFactoriesAndAllFinishes(): void
    {
        foreach (['first', 'middle'] as $failingName) {
            $expected = new \RuntimeException($failingName . ' factory failed');
            $events = [];
            $factory = static function (string $name) use (&$events, $failingName, $expected): Aggregator {
                return Aggregator::custom(static function () use (
                    &$events,
                    $name,
                    $failingName,
                    $expected,
                ): ScriptedCustomExecution {
                    if ($name === $failingName) {
                        $events[] = $name . ':factory';
                        throw $expected;
                    }

                    return new ScriptedCustomExecution($name, $events);
                });
            };
            $definition = combineAggregators(
                first: $factory('first'),
                middle: $factory('middle'),
                last: $factory('last'),
            );

            try {
                Sequence::from([1])->aggregate($definition);
                self::fail($failingName . ' factory exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            self::assertSame(
                $failingName === 'first' ? ['first:factory'] : ['first:factory', 'middle:factory'],
                $events,
            );
        }
    }

    public function testCombinedInitialCompletionFailureOccursAfterAllFactoriesAndStopsLaterWork(): void
    {
        $expected = new \RuntimeException('middle initial isComplete failed');
        $events = [];
        $sourceEvents = [];
        $first = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('first', $events);
        });
        $middle = Aggregator::custom(static function () use (&$events, $expected): ScriptedCustomExecution {
            return new ScriptedCustomExecution('middle', $events, failureStage: 'isComplete', failure: $expected);
        });
        $last = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('last', $events);
        });

        try {
            Sequence::from(new CustomIntRecordingIterator([1], $sourceEvents, 'source'))->aggregate(combineAggregators(
                first: $first,
                middle: $middle,
                last: $last,
            ));
            self::fail('The initial isComplete exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(
            ['first:factory', 'middle:factory', 'last:factory', 'first:isComplete', 'middle:isComplete'],
            $events,
        );
        self::assertSame([], $sourceEvents);
    }

    public function testCombinedPostAdvanceCompletionFailureSkipsLaterChildAndFinish(): void
    {
        $expected = new \RuntimeException('first post-advance isComplete failed');
        $events = [];
        $first = Aggregator::custom(static function () use (&$events, $expected): ScriptedCustomExecution {
            return new ScriptedCustomExecution(
                'first',
                $events,
                failureStage: 'isComplete',
                failure: $expected,
                failIsCompleteAfterAdvance: true,
            );
        });
        $later = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('later', $events);
        });

        try {
            Sequence::from([1])->aggregate(combineAggregators(first: $first, later: $later));
            self::fail('The post-advance isComplete exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertContains('first:advance:1', $events);
        self::assertNotContains('later:advance:1', $events);
        self::assertNotContains('first:finish', $events);
        self::assertNotContains('later:finish', $events);
    }

    public function testCombinedFinishFailureSkipsLaterFinishes(): void
    {
        $expected = new \RuntimeException('middle finish failed');
        $events = [];
        $first = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('first', $events);
        });
        $middle = Aggregator::custom(static function () use (&$events, $expected): ScriptedCustomExecution {
            return new ScriptedCustomExecution('middle', $events, failureStage: 'finish', failure: $expected);
        });
        $last = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('last', $events);
        });

        try {
            Sequence::empty()->aggregate(combineAggregators(first: $first, middle: $middle, last: $last));
            self::fail('The finish exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(
            ['first:finish', 'middle:finish'],
            array_values(array_filter($events, static fn(string $event): bool => str_ends_with($event, ':finish'))),
        );
    }

    public function testInitialCompletionFailurePreservesIdentityAndStopsBeforeSourceIteration(): void
    {
        $expected = new \RuntimeException('initial isComplete failed');
        $executionEvents = [];
        $sourceEvents = [];
        $definition = Aggregator::custom(static function () use (
            &$executionEvents,
            $expected,
        ): ScriptedCustomExecution {
            return new ScriptedCustomExecution(
                'custom',
                $executionEvents,
                failureStage: 'isComplete',
                failure: $expected,
            );
        });
        $sequence = Sequence::from(new CustomIntRecordingIterator([1], $sourceEvents, 'source'));

        try {
            $sequence->aggregate($definition);
            self::fail('The initial isComplete exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['custom:factory', 'custom:isComplete'], $executionEvents);
        self::assertSame([], $sourceEvents);
        $this->assertSequenceConsumed($sequence);
    }

    public function testPostAdvanceCompletionFailureStopsWithoutFinishing(): void
    {
        $expected = new \RuntimeException('post-advance isComplete failed');
        $events = [];
        $definition = Aggregator::custom(static function () use (&$events, $expected): ScriptedCustomExecution {
            return new ScriptedCustomExecution(
                'custom',
                $events,
                failureStage: 'isComplete',
                failure: $expected,
                failIsCompleteAfterAdvance: true,
            );
        });
        $sequence = Sequence::from([1, 2]);

        try {
            $sequence->aggregate($definition);
            self::fail('The post-advance isComplete exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['custom:factory', 'custom:isComplete', 'custom:advance:1', 'custom:isComplete'], $events);
        $this->assertSequenceConsumed($sequence);
    }

    public function testAdvanceAndFinishFailuresLeaveTheSequenceConsumedAndStopLaterActions(): void
    {
        foreach (['advance', 'finish'] as $stage) {
            $expected = new \RuntimeException($stage . ' failed');
            $events = [];
            $definition = Aggregator::custom(static function () use (
                &$events,
                $stage,
                $expected,
            ): ScriptedCustomExecution {
                return new ScriptedCustomExecution('custom', $events, failureStage: $stage, failure: $expected);
            });
            $sequence = Sequence::from([1, 2]);

            try {
                $sequence->aggregate($definition);
                self::fail($stage . ' exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            $expectedEvents = $stage === 'advance'
                ? ['custom:factory', 'custom:advance:1']
                : [
                    'custom:factory',
                    'custom:advance:1',
                    'custom:advance:2',
                    'custom:finish',
                ];
            self::assertSame($expectedEvents, self::withoutCompletionQueries($events));
            $this->assertSequenceConsumed($sequence);
        }
    }

    public function testSourceIterationFailureDoesNotFinishTheCustomExecution(): void
    {
        $expected = new \RuntimeException('source iteration failed');
        $events = [];
        $definition = Aggregator::custom(static function () use (&$events): ScriptedCustomExecution {
            return new ScriptedCustomExecution('custom', $events);
        });
        $source = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $sequence = Sequence::from($source());

        try {
            $sequence->aggregate($definition);
            self::fail('The source iteration exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['custom:factory', 'custom:advance:1'], self::withoutCompletionQueries($events));
        $this->assertSequenceConsumed($sequence);
    }

    public function testSourceResolutionFailureDoesNotCallCustomFactory(): void
    {
        $expected = new \RuntimeException('source failed');
        $factoryCalls = 0;
        $definition = Aggregator::custom(self::sourceFailingFactory($factoryCalls));
        $sequence = Sequence::from(new AggregatorThrowingSource($expected));

        try {
            $sequence->aggregate($definition);
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(0, $factoryCalls);
        try {
            $sequence->getIterator();
            self::fail('The failed sequence remained reusable.');
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testFactoryFailureLeavesTheSequenceConsumedAndTheDefinitionReusable(): void
    {
        $expected = new \RuntimeException('factory failed');
        $factoryCalls = 0;
        $failure = new AggregatorFailureSwitch(true);
        $definition = Aggregator::custom(static function () use (
            &$factoryCalls,
            $expected,
            $failure,
        ): SumCustomExecution {
            ++$factoryCalls;
            if ($failure->shouldFail) {
                $failure->shouldFail = false;
                throw $expected;
            }

            return new SumCustomExecution();
        });
        $sourceEvents = [];
        $source = new AggregatorRecordingSource($sourceEvents);
        $sequence = Sequence::from($source);

        try {
            $sequence->aggregate($definition);
            self::fail('The factory exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $factoryCalls);
        self::assertTrue($source->resolved);
        self::assertSame([], $sourceEvents);
        $this->assertSequenceConsumed($sequence);
        self::assertSame(3, Sequence::from([1, 2])->aggregate($definition));
        self::assertSame(2, $factoryCalls);
    }

    public function testConsumedSequenceRejectsBeforeCallingCustomFactory(): void
    {
        $factoryCalls = 0;
        $definition = Aggregator::custom(static function () use (&$factoryCalls): SumCustomExecution {
            ++$factoryCalls;

            return new SumCustomExecution();
        });
        $sequence = Sequence::from([1]);
        $sequence->getIterator();

        try {
            $sequence->aggregate($definition);
            self::fail('The consumed sequence was accepted.');
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(0, $factoryCalls);
    }

    /** @return callable(): ScriptedCustomExecution */
    private static function sourceFailingFactory(int &$calls): callable
    {
        return static function () use (&$calls): ScriptedCustomExecution {
            ++$calls;
            throw new \LogicException('The factory must not run.');
        };
    }

    /**
     * @template T
     * @param Sequence<T> $sequence
     */
    private function assertSequenceConsumed(Sequence $sequence): void
    {
        try {
            $sequence->getIterator();
            self::fail('The Sequence remained reusable.');
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @param list<string> $events
     * @return list<string>
     */
    private static function withoutCompletionQueries(array $events): array
    {
        return array_values(array_filter(
            $events,
            static fn(string $event): bool => !str_ends_with($event, ':isComplete'),
        ));
    }
}

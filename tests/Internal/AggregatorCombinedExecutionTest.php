<?php

declare(strict_types=1);

namespace Itera\Tests\Internal;

use Itera\Internal\AggregatorCombinedExecution;
use Itera\Internal\AggregatorExecution;
use Itera\Internal\AggregatorRunner;
use PHPUnit\Framework\TestCase;

final class AggregatorCombinedExecutionTest extends TestCase
{
    public function testEveryChildFinishesOnceInOrderAfterNormalEmptyAndEarlyTermination(): void
    {
        foreach ([[], [1, 2], [1, 2, 3]] as $values) {
            $events = [];
            $execution = AggregatorCombinedExecution::create([
                'first' => static function () use (&$events): AggregatorExecution {
                    return self::recordingExecution('first', $events, 1);
                },
                'second' => static function () use (&$events): AggregatorExecution {
                    return self::recordingExecution('second', $events, 2);
                },
            ]);

            $result = AggregatorRunner::execute($values, $execution);

            self::assertSame(['first' => 'first result', 'second' => 'second result'], $result);
            self::assertSame(1, array_count_values($events)['first:finish']);
            self::assertSame(1, array_count_values($events)['second:finish']);
            self::assertLessThan(
                array_search('second:finish', $events, strict: true),
                array_search('first:finish', $events, strict: true),
            );
        }
    }

    public function testCreationFailureStopsLaterFactoriesAndRunsNoFinish(): void
    {
        $expected = new \RuntimeException('initial failed');
        $events = [];

        try {
            AggregatorCombinedExecution::create([
                'first' => static function () use (&$events): AggregatorExecution {
                    $events[] = 'first:initial';

                    return self::recordingExecution('first', $events, 1);
                },
                'broken' => static function () use (&$events, $expected): never {
                    $events[] = 'broken:initial';
                    throw $expected;
                },
                'later' => static function () use (&$events): AggregatorExecution {
                    $events[] = 'later:initial';

                    return self::recordingExecution('later', $events, 1);
                },
            ]);
            self::fail('The initial exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['first:initial', 'broken:initial'], $events);
    }

    public function testSourceExhaustionFinishesChildrenThatRemainIncomplete(): void
    {
        $events = [];
        $execution = AggregatorCombinedExecution::create([
            'first' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('first', $events, 10);
            },
            'second' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('second', $events, 10);
            },
        ]);

        self::assertSame(
            ['first' => 'first result', 'second' => 'second result'],
            AggregatorRunner::execute([1, 2], $execution),
        );
        self::assertSame(
            ['first:step:1', 'second:step:1', 'first:step:2', 'second:step:2', 'first:finish', 'second:finish'],
            $events,
        );
    }

    public function testAdvanceFailureRunsNoFinishAndSkipsLaterChildForTheValue(): void
    {
        $expected = new \RuntimeException('advance failed');
        $events = [];
        $execution = AggregatorCombinedExecution::create([
            'first' => static function () use (&$events, $expected): AggregatorExecution {
                return new AggregatorExecution(static function (int $value) use (&$events, $expected): bool {
                    $events[] = 'first:' . $value;
                    throw $expected;
                }, static function () use (&$events): string {
                    $events[] = 'first:finish';

                    return 'unused';
                });
            },
            'second' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('second', $events, 1);
            },
        ]);

        try {
            AggregatorRunner::execute([1], $execution);
            self::fail('The advance exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['first:1'], $events);
    }

    public function testIterationFailureAfterOneChildCompletesRunsNoFinish(): void
    {
        $expected = new \RuntimeException('iteration failed');
        $events = [];
        $values = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $execution = AggregatorCombinedExecution::create([
            'first' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('first', $events, 1);
            },
            'second' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('second', $events, 10);
            },
        ]);

        try {
            AggregatorRunner::execute($values(), $execution);
            self::fail('The iteration exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['first:step:1', 'second:step:1'], $events);
    }

    public function testFinishFailureStopsLaterFinishesAndKeepsItsIdentity(): void
    {
        $expected = new \RuntimeException('finish failed');
        $events = [];
        $execution = AggregatorCombinedExecution::create([
            'first' => static function () use (&$events, $expected): AggregatorExecution {
                return new AggregatorExecution(static fn(mixed $_value): bool => false, static function () use (
                    &$events,
                    $expected,
                ): never {
                    $events[] = 'first:finish';
                    throw $expected;
                });
            },
            'second' => static function () use (&$events): AggregatorExecution {
                return self::recordingExecution('second', $events, 1);
            },
        ]);

        try {
            AggregatorRunner::execute([], $execution);
            self::fail('The finish exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['first:finish'], $events);
    }

    /**
     * @param list<string> $events
     * @return AggregatorExecution<int, string>
     */
    private static function recordingExecution(string $name, array &$events, int $completeAt): AggregatorExecution
    {
        $steps = 0;

        return new AggregatorExecution(static function (int $value) use (&$events, $name, $completeAt, &$steps): bool {
            $events[] = $name . ':step:' . $value;
            ++$steps;

            return $steps >= $completeAt;
        }, static function () use (&$events, $name): string {
            $events[] = $name . ':finish';

            return $name . ' result';
        });
    }
}

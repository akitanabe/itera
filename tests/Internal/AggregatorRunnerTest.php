<?php

declare(strict_types=1);

namespace Itera\Tests\Internal;

use Itera\Internal\AggregatorRunner;
use Itera\Tests\SequenceRecordingIterator;
use PHPUnit\Framework\TestCase;

final class AggregatorRunnerTest extends TestCase
{
    public function testEmptyInputSkipsStepAndCompleteBeforeFinishingOnce(): void
    {
        $events = [];
        $result = AggregatorRunner::execute(
            [],
            static function () use (&$events): int {
                $events[] = 'initial';

                return 5;
            },
            static function (int $state, mixed $_value) use (&$events): int {
                $events[] = 'step';

                return $state;
            },
            static function (int $_state) use (&$events): bool {
                $events[] = 'complete';

                return false;
            },
            static function (int $state) use (&$events): int {
                $events[] = 'finish';

                return $state;
            },
        );

        self::assertSame(5, $result);
        self::assertSame(['initial', 'finish'], $events);
    }

    public function testFinishRunsOnceAfterCompleteInputAndAfterAnEarlyDecision(): void
    {
        $normalFinishes = 0;
        $normal = AggregatorRunner::execute(
            [1, 2],
            static fn(): int => 0,
            static fn(int $state, int $value): int => $state + $value,
            static fn(int $_state): bool => false,
            static function (int $state) use (&$normalFinishes): int {
                ++$normalFinishes;

                return $state;
            },
        );

        $events = [];
        $earlyFinishes = 0;
        $early = AggregatorRunner::execute(
            new SequenceRecordingIterator([1, 2, 3], $events, 'source'),
            static fn(): int => 0,
            static function (int $state, mixed $value): int {
                if (!is_int($value)) {
                    throw new \LogicException('Expected an integer test value.');
                }

                return $state + $value;
            },
            static fn(int $state): bool => $state >= 3,
            static function (int $state) use (&$earlyFinishes): int {
                ++$earlyFinishes;

                return $state;
            },
        );

        self::assertSame(3, $normal);
        self::assertSame(1, $normalFinishes);
        self::assertSame(3, $early);
        self::assertSame(1, $earlyFinishes);
        self::assertSame(
            [
                'source:rewind',
                'source:valid:0',
                'source:current:0',
                'source:next:0',
                'source:valid:1',
                'source:current:1',
            ],
            $events,
        );
    }

    public function testInitialFailureDoesNotReadInputOrRunFinish(): void
    {
        $expected = new \RuntimeException('initial failed');
        $events = [];
        $finishes = 0;

        try {
            AggregatorRunner::execute(
                new SequenceRecordingIterator([1], $events, 'source'),
                static function () use ($expected): never {
                    throw $expected;
                },
                static fn(mixed $state, mixed $_value): mixed => $state,
                static fn(mixed $_state): bool => false,
                static function (mixed $state) use (&$finishes): mixed {
                    ++$finishes;

                    return $state;
                },
            );
            self::fail('The initial exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame([], $events);
        self::assertSame(0, $finishes);
    }

    public function testIterationFailureDoesNotRunFinish(): void
    {
        $expected = new \RuntimeException('iteration failed');
        $finishes = 0;
        $values = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };

        try {
            AggregatorRunner::execute(
                $values(),
                static fn(): int => 0,
                static fn(int $state, int $value): int => $state + $value,
                static fn(int $_state): bool => false,
                static function (int $state) use (&$finishes): int {
                    ++$finishes;

                    return $state;
                },
            );
            self::fail('The iteration exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(0, $finishes);
    }

    public function testStepAndCompleteFailuresDoNotRunFinish(): void
    {
        foreach (['step', 'complete'] as $stage) {
            $expected = new \RuntimeException($stage . ' failed');
            $finishes = 0;

            try {
                AggregatorRunner::execute(
                    [1],
                    static fn(): int => 0,
                    static function (int $state, int $value) use ($stage, $expected): int {
                        if ($stage === 'step') {
                            throw $expected;
                        }

                        return $state + $value;
                    },
                    static function (int $_state) use ($stage, $expected): bool {
                        if ($stage === 'complete') {
                            throw $expected;
                        }

                        return false;
                    },
                    static function (int $state) use (&$finishes): int {
                        ++$finishes;

                        return $state;
                    },
                );
                self::fail('The ' . $stage . ' exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            self::assertSame(0, $finishes);
        }
    }

    public function testFinishFailureKeepsItsIdentity(): void
    {
        $expected = new \RuntimeException('finish failed');
        $finishes = 0;

        try {
            AggregatorRunner::execute(
                [],
                static fn(): int => 0,
                static fn(int $state, int $_value): int => $state,
                static fn(int $_state): bool => false,
                static function () use (&$finishes, $expected): int {
                    ++$finishes;

                    return self::throwExpected($expected);
                },
            );
            self::fail('The finish exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $finishes);
    }

    private static function throwExpected(\RuntimeException $expected): int
    {
        throw $expected;
    }
}

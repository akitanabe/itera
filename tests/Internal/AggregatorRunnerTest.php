<?php

declare(strict_types=1);

namespace Itera\Tests\Internal;

use Itera\Internal\AggregatorExecution;
use Itera\Internal\AggregatorRunner;
use Itera\Tests\SequenceRecordingIterator;
use PHPUnit\Framework\TestCase;

final class AggregatorRunnerTest extends TestCase
{
    public function testEmptyInputFinishesOnceWithoutAdvancing(): void
    {
        $events = [];
        $execution = new AggregatorExecution(static function (mixed $_value) use (&$events): bool {
            $events[] = 'advance';

            return false;
        }, static function () use (&$events): int {
            $events[] = 'finish';

            return 5;
        });

        self::assertSame(5, AggregatorRunner::execute([], $execution));
        self::assertSame(['finish'], $events);
    }

    public function testNormalAndEarlyTerminationFinishOnceWithoutReadingPastCompletion(): void
    {
        $normalTotal = 0;
        $normalFinishes = 0;
        $normal = new AggregatorExecution(static function (int $value) use (&$normalTotal): bool {
            $normalTotal += $value;

            return false;
        }, static function () use (&$normalTotal, &$normalFinishes): int {
            ++$normalFinishes;

            return $normalTotal;
        });

        $events = [];
        $earlyTotal = 0;
        $earlyFinishes = 0;
        $early = new AggregatorExecution(static function (mixed $value) use (&$earlyTotal): bool {
            if (!is_int($value)) {
                throw new \LogicException('Expected an integer test value.');
            }
            $earlyTotal += $value;

            return $earlyTotal >= 3;
        }, static function () use (&$earlyTotal, &$earlyFinishes): int {
            ++$earlyFinishes;

            return $earlyTotal;
        });

        self::assertSame(3, AggregatorRunner::execute([1, 2], $normal));
        self::assertSame(1, $normalFinishes);
        self::assertSame(3, AggregatorRunner::execute(
            new SequenceRecordingIterator([1, 2, 3], $events, 'source'),
            $early,
        ));
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

    public function testIterationAndAdvanceFailuresDoNotRunFinish(): void
    {
        foreach (['iteration', 'advance'] as $stage) {
            $expected = new \RuntimeException($stage . ' failed');
            $finishes = 0;
            $values = static function () use ($stage, $expected): iterable {
                yield 1;
                if ($stage === 'iteration') {
                    throw $expected;
                }
            };
            $execution = new AggregatorExecution(static function (int $_value) use ($stage, $expected): bool {
                if ($stage === 'advance') {
                    throw $expected;
                }

                return false;
            }, static function () use (&$finishes): int {
                ++$finishes;

                return 0;
            });

            try {
                AggregatorRunner::execute($values(), $execution);
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
        $execution = new AggregatorExecution(static fn(mixed $_value): bool => false, static function () use (
            &$finishes,
            $expected,
        ): never {
            ++$finishes;
            throw $expected;
        });

        try {
            AggregatorRunner::execute([], $execution);
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $finishes);
    }
}

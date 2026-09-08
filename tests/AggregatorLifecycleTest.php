<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\any;
use function Itera\Aggregator\associate;

final class AggregatorLifecycleTest extends TestCase
{
    public function testAFailedDefinitionCanBeReusedAndTheFailedSequenceRemainsConsumed(): void
    {
        $expected = new \RuntimeException('predicate failed');
        $switch = new AggregatorFailureSwitch(self::initialFailureState());
        $definition = any(static function (int $value) use ($switch, $expected): bool {
            if ($switch->shouldFail) {
                throw $expected;
            }

            return $value === 2;
        });
        $failed = Sequence::from([1, 2]);

        try {
            $failed->aggregate($definition);
            self::fail('The predicate exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($failed);
        $switch->shouldFail = false;
        self::assertTrue(Sequence::from([1, 2])->aggregate($definition));
    }

    public function testOneDefinitionCanBeUsedReentrantlyWithoutSharingExecutionState(): void
    {
        $holder = new AggregatorDefinitionHolder();
        $nestedResults = [];
        $holder->definition = associate(static function (int $value) use ($holder, &$nestedResults): int {
            if ($value === 1) {
                $nestedResults[] = Sequence::from([2])->aggregate($holder->definition)->raw();
            }

            return $value;
        });

        self::assertSame([1 => 1, 3 => 3], Sequence::from([1, 3])->aggregate($holder->definition)->raw());
        self::assertSame([[2 => 2]], $nestedResults);
        self::assertSame([4 => 4], Sequence::from([4])->aggregate($holder->definition)->raw());
    }

    public function testAnEarlyDecisionDoesNotChangeTheDefinitionEmptyIdentity(): void
    {
        $definition = any(static fn(int $value): bool => $value === 1);

        self::assertTrue(Sequence::from([1, 2])->aggregate($definition));
        self::assertFalse(Sequence::empty()->aggregate($definition));
    }

    public function testAggregateRejectsASequenceWhoseConsumptionAlreadyStarted(): void
    {
        $sequence = Sequence::from([1]);
        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $sequence->aggregate(any(static fn(int $value): bool => $value === 1));
    }

    public function testSourceResolutionFailureKeepsItsIdentityConsumesTheSequenceAndLeavesTheDefinitionReusable(): void
    {
        $expected = new \RuntimeException('resolution failed');
        $source = new AggregatorThrowingSource($expected);
        $sequence = Sequence::from($source);
        $calls = 0;
        $definition = any(static function (int $value) use (&$calls): bool {
            ++$calls;

            return $value > 0;
        });

        try {
            $sequence->aggregate($definition);
            self::fail('The source resolution exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(0, $calls);
        $this->assertConsumed($sequence);
        self::assertTrue(Sequence::from([1])->aggregate($definition));
    }

    public function testIterationFailureKeepsItsIdentityAndDoesNotPoisonTheDefinition(): void
    {
        $expected = new \RuntimeException('iteration failed');
        $definition = any(static fn(int $value): bool => $value > 10);
        $source = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $sequence = Sequence::from($source());

        try {
            $sequence->aggregate($definition);
            self::fail('The iteration exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
        self::assertTrue(Sequence::from([11])->aggregate($definition));
    }

    public function testTakeZeroResolvesTheSourceButReadsNoValueOrCallback(): void
    {
        $events = [];
        $source = new AggregatorRecordingSource($events);
        $calls = 0;

        self::assertFalse(
            Sequence::from($source)
                ->take(0)
                ->aggregate(any(static function () use (&$calls): bool {
                    ++$calls;

                    return true;
                })),
        );
        self::assertTrue($source->resolved);
        self::assertSame([], $events);
        self::assertSame(0, $calls);
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

    private static function initialFailureState(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

final class SequenceScanTest extends TestCase
{
    public function testScanEmitsEachUpdatedStateWithoutEmittingTheSeed(): void
    {
        self::assertSame(
            [1, 3, 6],
            Sequence::from([1, 2, 3])
                ->scan(0, static fn(int $state, int $value): int => $state + $value)
                ->collect()
                ->values(),
        );
        self::assertSame(
            [],
            Sequence::empty()
                ->scan('seed', static fn(string $state, mixed $value): string => $state)
                ->collect()
                ->values(),
        );
    }

    public function testScanRegistrationsKeepIndependentRunningState(): void
    {
        $sequence = Sequence::from([1, 2])->scan(0, static fn(int $state, int $value): int => $state + $value)->scan(
            100,
            static fn(int $state, int $value): int => $state + $value,
        );

        self::assertSame([101, 104], $sequence->collect()->values());
    }

    public function testScanIsLazyAndReturnsTheSameSequence(): void
    {
        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'source';
            yield 1;
            $events[] = 'source-after';
            yield 2;
        };
        $sequence = Sequence::from($source());
        $actual = $sequence->scan(0, static function (int $state, int $value) use (&$events): int {
            $events[] = "step:{$value}";

            return $state + $value;
        });

        self::assertSame($sequence, $actual);
        self::assertSame([], $events);

        $iterator = $sequence->getIterator();
        self::assertSame([], $events);
        self::assertSame([1, 3], iterator_to_array($iterator));
        self::assertSame(['source', 'step:1', 'source-after', 'step:2'], $events);
    }

    public function testScanStopsUpdatingStateWhenDownstreamStops(): void
    {
        $read = [];
        $steps = [];
        $source = static function () use (&$read): iterable {
            foreach ([1, 2, 3] as $value) {
                $read[] = $value;
                yield $value;
            }
        };

        $result = Sequence::from($source())
            ->scan(0, static function (int $state, int $value) use (&$steps): int {
                $steps[] = $value;

                return $state + $value;
            })
            ->take(2)
            ->collect();

        self::assertSame([1, 3], $result->values());
        self::assertSame([1, 2], $read);
        self::assertSame([1, 2], $steps);
    }

    public function testScanPreservesObjectStateAndOutputIdentity(): void
    {
        $state = new \DateTime('2026-09-09');
        $outputs = Sequence::from([1, 2])
            ->scan($state, static function (\DateTime $state, int $value): \DateTime {
                $state->modify("+{$value} days");

                return $state;
            })
            ->collect()
            ->values();

        self::assertSame([$state, $state], $outputs);
        self::assertSame('2026-09-12', $state->format('Y-m-d'));
    }

    public function testScanStepExceptionKeepsItsIdentityAndConsumesTheSequence(): void
    {
        $expected = new \RuntimeException('scan failed');
        $sequence = Sequence::of(1)->scan(0, static function () use ($expected): never {
            throw $expected;
        });

        try {
            $sequence->collect();
            self::fail('The scan exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->scan(0, static fn(int $state, mixed $value): int => $state);
    }

    public function testScanRejectsOperatorsAfterConsumption(): void
    {
        $sequence = Sequence::from([1]);
        $sequence->collect();

        $this->expectException(SequenceConsumedException::class);
        $sequence->scan(0, static fn(int $state, mixed $value): int => $state);
    }
}

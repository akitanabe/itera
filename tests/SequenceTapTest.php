<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

final class SequenceTapTest extends TestCase
{
    public function testTapRunsLazilyAtItsDeclaredPositionAndForwardsValues(): void
    {
        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'source:1';
            yield 1;
            $events[] = 'source:2';
            yield 2;
        };
        $sequence = Sequence::from($source());

        $actual = $sequence->tap(static function (int $value) use (&$events): void {
            $events[] = "tap:{$value}";
        });

        self::assertSame($sequence, $actual);
        self::assertSame([], $events);
        self::assertSame([1, 2], $sequence->collect()->values());
        self::assertSame(['source:1', 'tap:1', 'source:2', 'tap:2'], $events);
    }

    public function testTapDoesNotRunForAnEmptySequence(): void
    {
        $events = [];

        $result = Sequence::empty()->tap(static function (mixed $value) use (&$events): void {
            $events[] = $value;
        })->collect();

        self::assertSame([], $result->values());
        self::assertSame([], $events);
    }

    public function testTapRunsOnlyForValuesThatReachItsPosition(): void
    {
        $before = [];
        $after = [];

        $beforeResult = Sequence::from([1, 2, 3])->tap(static function (int $value) use (&$before): void {
            $before[] = $value;
        })->filter(static fn(int $value): bool => $value > 1)->collect();
        $afterResult = Sequence::from([1, 2, 3])
            ->filter(static fn(int $value): bool => $value > 1)
            ->tap(static function (int $value) use (&$after): void {
                $after[] = $value;
            })
            ->collect();

        self::assertSame([2, 3], $beforeResult->values());
        self::assertSame([1, 2, 3], $before);
        self::assertSame([2, 3], $afterResult->values());
        self::assertSame([2, 3], $after);
    }

    public function testTapObservesValuesBeforeOrAfterScanAtTheDeclaredPosition(): void
    {
        $before = [];
        $after = [];

        $beforeResult = Sequence::from([1, 2])->tap(static function (int $value) use (&$before): void {
            $before[] = $value;
        })->scan(0, static fn(int $state, int $value): int => $state + $value)->collect();
        $afterResult = Sequence::from([1, 2])
            ->scan(0, static fn(int $state, int $value): int => $state + $value)
            ->tap(static function (int $value) use (&$after): void {
                $after[] = $value;
            })
            ->collect();

        self::assertSame([1, 3], $beforeResult->values());
        self::assertSame([1, 2], $before);
        self::assertSame([1, 3], $afterResult->values());
        self::assertSame([1, 3], $after);
    }

    public function testTapPreservesObjectIdentityAndMutations(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $values = [$first, $second];
        $output = Sequence::from($values)
            ->tap(static function (\stdClass $value): void {
                $value->seen = true;
            })
            ->collect()
            ->values();

        self::assertSame($values, $output);
        self::assertTrue($first->seen);
        self::assertTrue($second->seen);
    }

    public function testTapIgnoresTheCallbackReturnValue(): void
    {
        $events = [];
        // @phpstan-ignore argument.type (A non-void callback intentionally verifies that tap ignores its return value.)
        $sequence = Sequence::from([1])->tap(static function (int $value) use (&$events): int {
            $events[] = $value;

            return 99;
        });

        self::assertSame([1], $sequence->collect()->values());
        self::assertSame([1], $events);
    }

    public function testTapStopsEffectsAndReadsWhenDownstreamStops(): void
    {
        $read = [];
        $effects = [];
        $source = static function () use (&$read): iterable {
            foreach ([1, 2, 3] as $value) {
                $read[] = $value;
                yield $value;
            }
        };

        $result = Sequence::from($source())
            ->tap(static function (int $value) use (&$effects): void {
                $effects[] = $value;
            })
            ->take(2)
            ->collect();

        self::assertSame([1, 2], $result->values());
        self::assertSame([1, 2], $read);
        self::assertSame([1, 2], $effects);
    }

    public function testTapPropagatesTheSameExceptionAndConsumesTheSequence(): void
    {
        $expected = new \RuntimeException('tap failed');
        $sequence = Sequence::of(1)->tap(static function (mixed $value) use ($expected): void {
            throw $expected;
        });

        try {
            $sequence->collect();
            self::fail('The tap exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->tap(static function (mixed $value): void {});
    }
}

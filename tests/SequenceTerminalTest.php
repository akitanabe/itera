<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;
use TypeError;

final class SequenceTerminalTest extends TestCase
{
    public function testToArrayReturnsAListAndConsumesEveryFiniteValue(): void
    {
        $seen = [];
        $source = static function () use (&$seen): iterable {
            foreach (['first' => 'alpha', 8 => 'beta'] as $key => $value) {
                $seen[] = $key;
                yield $key => $value;
            }
        };

        self::assertSame(['alpha', 'beta'], Sequence::from($source())->toArray());
        self::assertSame(['first', 8], $seen);
    }

    public function testFirstReturnsNullForEmptyAndStoredNullAndStopsAfterTheFirstOutput(): void
    {
        $emptyResult = Sequence::from($this->mixedValues())->first();
        self::assertNull($emptyResult);
        self::assertNull(Sequence::from([null, 'later'])->first());

        $source = static function (): iterable {
            yield 'first';
            throw new \RuntimeException('first read past its result.');
        };

        self::assertSame('first', Sequence::from($source())->first());
    }

    public function testCountCountsPipelineResultsAndHonorsTakeLimit(): void
    {
        $source = static function (): iterable {
            yield 1;
            yield 2;
            throw new \RuntimeException('count read past take(2).');
        };

        self::assertSame(2, Sequence::from($source())->take(2)->count());
        self::assertSame(
            1,
            \Itera\Collection::from([1, 2, 3])
                ->sequence()
                ->filter(static fn(int $value): bool => $value === 2)
                ->count(),
        );
        self::assertSame(0, Sequence::empty()->count());
    }

    public function testAnyReceivesOnlyTheValueAndStopsAtTheFirstTrue(): void
    {
        $seen = [];
        $source = static function (): \Generator {
            yield 1;
            yield 2;
            throw new \RuntimeException('any read past its result.');
        };
        $result = Sequence::from($source())->any(static function (int $value) use (&$seen): bool {
            $seen[] = [$value, func_num_args()];

            return $value === 2;
        });

        self::assertTrue($result);
        self::assertSame([[1, 1], [2, 1]], $seen);
        self::assertFalse(Sequence::from([1, 2])->any(static fn(int $value): bool => $value > 2));
        self::assertFalse(Sequence::empty()->any(static fn(mixed $value): bool => true));
    }

    public function testAllReceivesOnlyTheValueAndStopsAtTheFirstFalse(): void
    {
        $seen = [];
        $source = static function (): \Generator {
            yield 1;
            yield 2;
            throw new \RuntimeException('all read past its result.');
        };
        $result = Sequence::from($source())->all(static function (int $value) use (&$seen): bool {
            $seen[] = [$value, func_num_args()];

            return $value < 2;
        });

        self::assertFalse($result);
        self::assertSame([[1, 1], [2, 1]], $seen);
        self::assertTrue(Sequence::from([1, 2])->all(static fn(int $value): bool => $value < 3));
        self::assertTrue(Sequence::empty()->all(static fn(mixed $value): bool => false));
    }

    public function testAnyAndAllRejectNonBooleanResults(): void
    {
        foreach (['any', 'all'] as $method) {
            $sequence = Sequence::of(1);

            try {
                new \ReflectionMethod(Sequence::class, $method)->invoke(
                    $sequence,
                    static fn(int $value): int => $value,
                );
                self::fail($method . ' accepted a non-boolean predicate result.');
            } catch (TypeError) {
                $this->addToAssertionCount(1);
            }

            $this->assertConsumed($sequence);
        }
    }

    public function testFoldUsesStateThenValueInOrderAndKeepsTheInitialValueForEmptyInput(): void
    {
        $initial = new \ArrayObject(['initial']);

        self::assertSame($initial, Sequence::empty()->fold($initial, static fn($state, mixed $value) => $state));
        self::assertSame('start:1:2:3', Sequence::from([1, 2, 3])->fold('start', static function (
            string $state,
            int $value,
        ): string {
            self::assertSame(2, func_num_args());

            return $state . ':' . $value;
        }));
    }

    public function testTerminalCallbackAndSourceExceptionsKeepTheirIdentity(): void
    {
        foreach (['any', 'all', 'fold'] as $method) {
            $expected = new \RuntimeException($method . ' failed');
            $sequence = Sequence::of(1);
            $callback = static function () use ($expected): never {
                throw $expected;
            };
            $arguments = $method === 'fold' ? [0, $callback] : [$callback];

            try {
                new \ReflectionMethod(Sequence::class, $method)->invokeArgs($sequence, $arguments);
                self::fail('The callback exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            $this->assertConsumed($sequence);
        }

        $expected = new \RuntimeException('source failed');
        $source = static function () use ($expected): iterable {
            yield 'before';
            throw $expected;
        };
        $sequence = Sequence::from($source());

        try {
            $sequence->toArray();
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    /**
     * @template V
     * @param Sequence<V> $sequence
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

    /** @return iterable<mixed> */
    private function mixedValues(): iterable
    {
        return [];
    }
}

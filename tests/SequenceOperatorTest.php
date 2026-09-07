<?php

declare(strict_types=1);

namespace Itera\Tests;

use InvalidArgumentException;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

final class SequenceOperatorTest extends TestCase
{
    public function testOperatorsMutateTheSameSequenceWithoutEvaluatingIt(): void
    {
        $calls = [];
        $sequence = Sequence::from(
            (static function () use (&$calls): iterable {
                $calls[] = 'source';
                yield 1;
            })(),
        );

        self::assertSame($sequence, $sequence->map(static function (int $value) use (&$calls): int {
            $calls[] = 'map';
            return $value + 1;
        }));
        self::assertSame($sequence, $sequence->filter(static function (int $value) use (&$calls): bool {
            $calls[] = 'filter';
            return true;
        }));
        self::assertSame($sequence, $sequence->flatMap(static function (int $value) use (&$calls): iterable {
            $calls[] = 'flatMap';
            return [$value];
        }));
        self::assertSame($sequence, $sequence->drop(0));
        self::assertSame($sequence, $sequence->take(1));
        self::assertSame([], $calls);

        self::assertSame([2], iterator_to_array($sequence));
        self::assertSame(['source', 'map', 'filter', 'flatMap'], $calls);
    }

    public function testMapAndFilterReceiveOnlyTheValue(): void
    {
        $argumentCounts = [];
        $sequence = Sequence::from(['named' => 2])->map(static function (int $value) use (&$argumentCounts): int {
            $argumentCounts[] = func_num_args();
            return $value * 3;
        })->filter(static function (int $value) use (&$argumentCounts): bool {
            $argumentCounts[] = func_num_args();
            return $value === 6;
        });

        self::assertSame([6], iterator_to_array($sequence));
        self::assertSame([1, 1], $argumentCounts);
    }

    public function testFlatMapStreamsIterableValuesAndDiscardsEveryKey(): void
    {
        $argumentCounts = [];
        $sequence = Sequence::from(['empty' => 0, 'one' => 1, 'many' => 2])->flatMap(static function (int $value) use (
            &$argumentCounts,
        ): iterable {
            $argumentCounts[] = func_num_args();

            return match ($value) {
                0 => [],
                1 => ['one' => 1],
                default => ['left' => $value, 99 => $value * 10],
            };
        });

        self::assertSame([1, 2, 20], iterator_to_array($sequence));
        self::assertSame([1, 1, 1], $argumentCounts);
    }

    public function testFilterRejectsANonBooleanResultAndConsumesTheSequence(): void
    {
        $sequence = Sequence::of(1);
        $filter = new ReflectionMethod($sequence, 'filter');
        $filter->invoke($sequence, static fn(mixed $value): int => is_int($value) ? $value : 0);

        try {
            iterator_to_array($sequence);
            self::fail('A non-boolean predicate result was accepted.');
        } catch (TypeError $exception) {
            self::assertInstanceOf(TypeError::class, $exception);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->map(static fn(mixed $value): mixed => $value);
    }

    public function testFlatMapRejectsANonIterableResultAndConsumesTheSequence(): void
    {
        $sequence = Sequence::of(1);
        $flatMap = new ReflectionMethod($sequence, 'flatMap');
        $flatMap->invoke($sequence, static fn(mixed $value): int => is_int($value) ? $value : 0);

        try {
            iterator_to_array($sequence);
            self::fail('A non-iterable mapper result was accepted.');
        } catch (TypeError $exception) {
            self::assertInstanceOf(TypeError::class, $exception);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->take(1);
    }

    public function testCallbackExceptionsKeepTheirIdentityAndConsumeTheSequence(): void
    {
        $expected = new \RuntimeException('callback failed');
        $sequence = Sequence::of(1)->map(static function () use ($expected): never {
            throw $expected;
        });

        try {
            iterator_to_array($sequence);
            self::fail('The callback exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->drop(0);
    }

    public function testCallbackCannotReenterTheSequence(): void
    {
        $sequence = Sequence::from([1]);
        $sequence->map(static function (int $value) use ($sequence): int {
            $sequence->filter(static fn(): bool => true);
            return $value;
        });

        $this->expectException(SequenceConsumedException::class);
        iterator_to_array($sequence);
    }

    public function testNegativeTakeAndDropLeaveThePipelineUnchanged(): void
    {
        $sequence = Sequence::of(1, 2);

        foreach (['take', 'drop'] as $method) {
            try {
                $sequence->{$method}(-1);
                self::fail($method . ' accepted a negative count.');
            } catch (InvalidArgumentException $exception) {
                self::assertInstanceOf(InvalidArgumentException::class, $exception);
            }
        }

        self::assertSame([1, 2], iterator_to_array($sequence));
    }

    public function testTakeAndDropMayExceedTheInputLength(): void
    {
        self::assertSame([1, 2], iterator_to_array(Sequence::of(1, 2)->take(10)));
        self::assertSame([], iterator_to_array(Sequence::of(1, 2)->drop(10)));
    }

    public function testEveryOperatorRejectsAConsumedSequence(): void
    {
        $operations = [
            static fn(Sequence $sequence): Sequence => $sequence->map(static fn(mixed $value): mixed => $value),
            static fn(Sequence $sequence): Sequence => $sequence->filter(static fn(mixed $value): bool => true),
            static fn(Sequence $sequence): Sequence => $sequence->flatMap(static fn(mixed $value): iterable => [
                $value,
            ]),
            static fn(Sequence $sequence): Sequence => $sequence->take(1),
            static fn(Sequence $sequence): Sequence => $sequence->drop(1),
        ];

        foreach ($operations as $operation) {
            $sequence = Sequence::empty();
            $sequence->getIterator();

            try {
                $operation($sequence);
                self::fail('A consumed sequence accepted an operator.');
            } catch (SequenceConsumedException) {
                self::addToAssertionCount(1);
            }
        }

        $sequence = Sequence::empty();
        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $sequence->take(-1);
    }
}

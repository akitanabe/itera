<?php

declare(strict_types=1);

namespace Itera\Tests;

use InvalidArgumentException;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

/** @mago-expect lint:too-many-methods */
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

    public function testUntilIncludesTheFirstMatchingValueAndHandlesBoundaries(): void
    {
        self::assertSame([1], Sequence::from([1, 2, 3])->until(static fn(int $value): bool => $value === 1)->toArray());
        self::assertSame(
            [1, 2, 3],
            Sequence::from([1, 2, 3])->until(static fn(int $value): bool => $value === 3)->toArray(),
        );
        self::assertSame(
            [1, 2],
            Sequence::from([1, 2, 3])->until(static fn(int $value): bool => $value === 2)->toArray(),
        );
        self::assertSame(
            [1, 2, 3],
            Sequence::from([1, 2, 3])->until(static fn(int $value): bool => $value === 9)->toArray(),
        );
        self::assertSame(
            [],
            Sequence::empty()
                ->until(static fn(mixed $value): bool => true)
                ->toArray(),
        );
    }

    public function testUntilIsLazyMutableAndReceivesOnlyTheValue(): void
    {
        $calls = [];
        $sequence = Sequence::from(
            (static function () use (&$calls): iterable {
                $calls[] = 'source';
                yield 1;
                $calls[] = 'after';
                yield 2;
            })(),
        );

        self::assertSame($sequence, $sequence->until(static function (int $value) use (&$calls): bool {
            $calls[] = ['until', $value, func_num_args()];

            return $value === 2;
        }));
        self::assertSame([], $calls);

        $iterator = $sequence->getIterator();
        self::assertCount(0, $calls);
        self::assertSame([1, 2], iterator_to_array($iterator));
        self::assertSame(['source', ['until', 1, 1], 'after', ['until', 2, 1]], $calls);
    }

    public function testUntilPreservesNullAndFalseValues(): void
    {
        self::assertSame(
            [null, false],
            Sequence::of(null, false, 'later')->until(static fn(mixed $value): bool => $value === false)->toArray(),
        );
    }

    public function testSkipUntilRetainsTheFirstMatchAndAllFollowingValues(): void
    {
        self::assertSame(
            [1, 2, 3],
            Sequence::from([1, 2, 3])->skipUntil(static fn(int $value): bool => $value === 1)->toArray(),
        );
        self::assertSame(
            [3],
            Sequence::from([1, 2, 3])->skipUntil(static fn(int $value): bool => $value === 3)->toArray(),
        );
        self::assertSame(
            [2, 3],
            Sequence::from([1, 2, 3])->skipUntil(static fn(int $value): bool => $value === 2)->toArray(),
        );
        $noMatchCalls = [];
        self::assertSame(
            [],
            Sequence::from([1, 2, 3])->skipUntil(static function (int $value) use (&$noMatchCalls): bool {
                $noMatchCalls[] = $value;

                return false;
            })->toArray(),
        );
        self::assertSame([1, 2, 3], $noMatchCalls);
        self::assertSame(
            [],
            Sequence::empty()
                ->skipUntil(static fn(mixed $value): bool => true)
                ->toArray(),
        );
    }

    public function testSkipUntilIsLazyMutableAndReceivesOnlyValuesUntilTheFirstMatch(): void
    {
        $calls = [];
        $sequence = Sequence::from(
            (static function () use (&$calls): iterable {
                $calls[] = 'source';
                yield 1;
                $calls[] = 'after';
                yield 2;
                $calls[] = 'after-match';
                yield 3;
            })(),
        );

        self::assertSame($sequence, $sequence->skipUntil(static function (int $value) use (&$calls): bool {
            $calls[] = ['skipUntil', $value, func_num_args()];

            return $value === 2;
        }));
        self::assertSame([], $calls);

        $iterator = $sequence->getIterator();
        self::assertCount(0, $calls);
        self::assertSame([2, 3], iterator_to_array($iterator));
        self::assertSame(['source', ['skipUntil', 1, 1], 'after', ['skipUntil', 2, 1], 'after-match'], $calls);
    }

    public function testSkipUntilPreservesNullFalseValuesAndListKeys(): void
    {
        self::assertSame(
            [null, false, 'later'],
            Sequence::from(['null' => null, 'false' => false, 'later' => 'later'])->skipUntil(
                static fn(mixed $value): bool => $value === null,
            )->toArray(),
        );
    }

    public function testSkipUntilRunsAfterMapAndFilterInDeclarationOrder(): void
    {
        $seen = [];
        $values = Sequence::from([1, 2, 3])
            ->map(static fn(int $value): int => $value * 10)
            ->filter(static fn(int $value): bool => $value >= 20)
            ->skipUntil(static function (int $value) use (&$seen): bool {
                $seen[] = $value;

                return $value === 20;
            })
            ->toArray();

        self::assertSame([20, 30], $values);
        self::assertSame([20], $seen);
    }

    public function testSkipUntilRegistrationsAndSequencesHaveIndependentState(): void
    {
        $firstCalls = [];
        $secondCalls = [];
        $separateCalls = [];
        $sequence = Sequence::from([1, 2, 3, 4])->skipUntil(static function (int $value) use (&$firstCalls): bool {
            $firstCalls[] = $value;

            return $value === 2;
        })->skipUntil(static function (int $value) use (&$secondCalls): bool {
            $secondCalls[] = $value;

            return $value === 3;
        });
        $separate = Sequence::from([1, 2, 3])->skipUntil(static function (int $value) use (&$separateCalls): bool {
            $separateCalls[] = $value;

            return $value === 2;
        });

        self::assertSame([3, 4], $sequence->toArray());
        self::assertSame([1, 2], $firstCalls);
        self::assertSame([2, 3], $secondCalls);

        self::assertSame([2, 3], $separate->toArray());
        self::assertSame([1, 2], $separateCalls);
    }

    public function testSkipUntilUsesPhpTruthinessForPredicateResults(): void
    {
        $object = new \stdClass();
        $sequence = Sequence::of(0, '', null, $object, 'later');
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $sequence->skipUntil(static fn(mixed $value): mixed => $value);

        self::assertSame([$object, 'later'], $sequence->toArray());
    }

    public function testUntilUsesPhpTruthinessForPredicateResults(): void
    {
        $sequence = Sequence::from([0, '', 2, 3]);
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $sequence->until(static fn(mixed $value): mixed => $value);

        self::assertSame([0, '', 2], $sequence->toArray());
    }

    public function testUntilRunsAfterMapAndFilterInDeclarationOrder(): void
    {
        $seen = [];
        $values = Sequence::from([1, 2, 3])
            ->map(static fn(int $value): int => $value * 10)
            ->filter(static fn(int $value): bool => $value >= 20)
            ->until(static function (int $value) use (&$seen): bool {
                $seen[] = $value;

                return $value === 20;
            })
            ->toArray();

        self::assertSame([20], $values);
        self::assertSame([20], $seen);
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

    public function testFilterUsesPhpTruthinessForPredicateResults(): void
    {
        $object = new \stdClass();
        $sequence = Sequence::of(1, 0, 'value', '', null, $object);
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $sequence->filter(static fn(mixed $value): mixed => $value);

        self::assertSame([1, 'value', $object], $sequence->toArray());
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

    public function testTakeZeroDoesNotSkipLaterCountValidationOrConsumedChecks(): void
    {
        $sequence = Sequence::of(1)->take(0);
        foreach (['take', 'drop'] as $method) {
            try {
                $sequence->{$method}(-1);
                self::fail($method . ' accepted a negative count after take(0).');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame([], $sequence->toArray());

        $sequence = Sequence::of(1)->take(0);
        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $sequence->take(-1);
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

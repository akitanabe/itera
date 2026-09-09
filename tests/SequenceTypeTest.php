<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function PHPStan\Testing\assertType;

/** @mago-expect lint:too-many-methods */
final class SequenceTypeTest extends TypeInferenceTestCase
{
    public function testInferredTypesMatchTheDeclaredExpectations(): void
    {
        foreach (self::gatherAssertTypes(__FILE__) as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    #[DataProvider('flags')]
    public function testMutableTypeChangesRemainVisibleToStaticAnalysis(bool $flag): void
    {
        $sequence = Sequence::from([1, 2]);
        $same = $sequence->map(static fn(int $value): string => (string) $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<decimal-int-string>', $sequence);
            assertType('Itera\\Sequence<decimal-int-string>', $same);
        }

        $floats = $sequence->flatMap(static fn(string $value): iterable => [(float) $value]);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<float>', $sequence);
            assertType('Itera\\Sequence<float>', $floats);
            assertType('Itera\\Sequence<bool>', Sequence::from([1])->map(
                static fn(int $value): string => (string) $value,
            )->flatMap(static fn(string $value): iterable => [$flag]));
        }

        self::assertSame([1.0, 2.0], iterator_to_array($floats));
    }

    public function testUntilPreservesTheElementType(): void
    {
        $sequence = Sequence::from([1, 2])->until(static fn(int $value): bool => $value === 2);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<int>', $sequence);
        }

        self::assertSame([1, 2], $sequence->collect()->values());
    }

    public function testConcatPreservesTheSharedElementTypeAcrossItsInputs(): void
    {
        $first = Sequence::from([1]);
        $second = Sequence::from([2]);
        $combined = Sequence::concat($first, $second);
        $single = Sequence::concat(Sequence::from(['value']));
        $withEmpty = Sequence::concat(Sequence::empty(), Sequence::from([3]));
        $mappedSource = Sequence::from([1]);
        $mappedSource->map(static fn(int $value): string => (string) $value);
        $mappedInput = Sequence::concat($mappedSource);
        $mapped = Sequence::concat(Sequence::from([1, 2]))->map(static fn(int $value): string => (string) $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<int>', $combined);
            assertType('Itera\\Sequence<string>', $single);
            assertType('Itera\\Sequence<int>', $withEmpty);
            assertType('Itera\\Sequence<decimal-int-string>', $mappedInput);
            assertType('Itera\\Sequence<decimal-int-string>', $mapped);
        }

        self::assertSame([1, 2], $combined->collect()->values());
        self::assertSame(['value'], $single->collect()->values());
        self::assertSame([3], $withEmpty->collect()->values());
        self::assertSame(['1'], $mappedInput->collect()->values());
        self::assertSame(['1', '2'], $mapped->collect()->values());
    }

    public function testUntilPreservesTheElementTypeAfterMap(): void
    {
        $sequence = Sequence::from([1, 2])->map(static fn(int $value): string => (string) $value)->until(
            static fn(string $value): bool => $value === '2',
        );

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<decimal-int-string>', $sequence);
        }

        self::assertSame(['1', '2'], $sequence->collect()->values());
    }

    public function testUntilPreservesTheElementTypeAfterFlatMap(): void
    {
        $sequence = Sequence::from([1, 2])->flatMap(static fn(int $value): iterable => [(float) $value])->until(
            static fn(float $value): bool => $value === 2.0,
        );

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<float>', $sequence);
        }

        self::assertSame([1.0, 2.0], $sequence->collect()->values());
    }

    public function testScanUpdatesTheReceiverAndResultTypeToTheStateType(): void
    {
        $sequence = Sequence::from([1, 2]);
        $same = $sequence->scan(0.0, static fn(float $state, int $value): float => $state + $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<float>', $sequence);
            assertType('Itera\\Sequence<float>', $same);
        }

        self::assertSame([1.0, 3.0], $sequence->collect()->values());
    }

    public function testTapPreservesTheElementTypeAndReceiverType(): void
    {
        $sequence = Sequence::from([1, 2]);
        $same = $sequence->tap(static function (int $value): void {});

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<int>', $sequence);
            assertType('Itera\\Sequence<int>', $same);
        }

        self::assertSame([1, 2], $sequence->collect()->values());
    }

    public function testChunkUpdatesTheReceiverAndResultToCollectionsOfTheElementType(): void
    {
        $sequence = Sequence::from([1, 2]);
        $same = $sequence->chunk(2);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<Itera\\Collection<int>>', $sequence);
            assertType('Itera\\Sequence<Itera\\Collection<int>>', $same);
        }

        self::assertSame([1, 2], $same->collect()->first()?->values());
    }

    public function testSkipUntilPreservesTheElementType(): void
    {
        $sequence = Sequence::from([1, 2])->skipUntil(static fn(int $value): bool => $value === 2);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<int>', $sequence);
        }

        self::assertSame([2], $sequence->collect()->values());
    }

    public function testSkipUntilPreservesTheElementTypeAfterMap(): void
    {
        $sequence = Sequence::from([1, 2])->map(static fn(int $value): string => (string) $value)->skipUntil(
            static fn(string $value): bool => $value === '2',
        );

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<decimal-int-string>', $sequence);
        }

        self::assertSame(['2'], $sequence->collect()->values());
    }

    public function testSkipUntilPreservesTheElementTypeAfterFlatMap(): void
    {
        $sequence = Sequence::from([1, 2])->flatMap(static fn(int $value): iterable => [(float) $value])->skipUntil(
            static fn(float $value): bool => $value === 2.0,
        );

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<float>', $sequence);
        }

        self::assertSame([2.0], $sequence->collect()->values());
    }

    public function testTerminalResultTypesRemainVisibleToStaticAnalysis(): void
    {
        $sequence = Sequence::from([1, 2]);
        $collection = $sequence->collect();
        $sum = Sequence::from([1, 2])->fold(0.0, static fn(float $state, int $value): float => $state + $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $collection);
            assertType('float', $sum);
        }

        self::assertInstanceOf(Collection::class, $collection);
        self::assertSame([1, 2], $collection->values());
        self::assertSame(3.0, $sum);
    }

    /** @return iterable<array{bool}> */
    public static function flags(): iterable
    {
        yield [false];
        yield [true];
    }
}

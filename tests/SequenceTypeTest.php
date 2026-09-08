<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function PHPStan\Testing\assertType;

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

    public function testTerminalResultTypesRemainVisibleToStaticAnalysis(): void
    {
        $sequence = Sequence::from([1, 2]);
        $array = $sequence->toArray();
        $sum = Sequence::from([1, 2])->fold(0.0, static fn(float $state, int $value): float => $state + $value);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('list<int>', $array);
            assertType('float', $sum);
        }

        self::assertSame([1, 2], $array);
        self::assertSame(3.0, $sum);
    }

    /** @return iterable<array{bool}> */
    public static function flags(): iterable
    {
        yield [false];
        yield [true];
    }
}

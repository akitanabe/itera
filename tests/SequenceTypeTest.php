<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function PHPStan\Testing\assertType;

final class SequenceTypeTest extends TestCase
{
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

    /** @return iterable<array{bool}> */
    public static function flags(): iterable
    {
        yield [false];
        yield [true];
    }
}

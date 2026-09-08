<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Map;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class SequenceTerminalTest extends TestCase
{
    public function testCollectMaterializesIntoACollection(): void
    {
        self::assertContains('collect', get_class_methods(Sequence::class));
        self::assertNotContains('toArray', get_class_methods(Sequence::class));
        self::assertNotContains('toCollection', get_class_methods(Sequence::class));

        $materialized = Sequence::from(['named' => 'alpha', 8 => 'beta'])->collect();

        self::assertInstanceOf(Collection::class, $materialized);
        self::assertSame(['alpha', 'beta'], $materialized->values());
        self::assertSame([], Sequence::empty()->collect()->values());
    }

    public function testAssociateIsAMaterializationMethodAndReturnsAMap(): void
    {
        self::assertContains('associate', get_class_methods(Sequence::class));
        self::assertInstanceOf(Map::class, Sequence::empty()->associate(static fn(mixed $value): string => 'unused'));
        self::assertSame(
            ['a' => 'alpha', 'b' => 'beta'],
            Sequence::from(['alpha', 'beta'])->associate(static fn(string $value): string => $value[0])->raw(),
        );
    }

    public function testCollectReturnsACollectionAndConsumesEveryFiniteValue(): void
    {
        $seen = [];
        $source = static function () use (&$seen): iterable {
            foreach (['first' => 'alpha', 8 => 'beta'] as $key => $value) {
                $seen[] = $key;
                yield $key => $value;
            }
        };

        self::assertSame(['alpha', 'beta'], Sequence::from($source())->collect()->values());
        self::assertSame(['first', 8], $seen);
    }

    #[TestWith(['first'], 'first')]
    #[TestWith(['last'], 'last')]
    #[TestWith(['contains'], 'contains')]
    #[TestWith(['count'], 'count')]
    #[TestWith(['any'], 'any')]
    #[TestWith(['all'], 'all')]
    #[TestWith(['reduce'], 'reduce')]
    public function testQueryMethodIsAbsentFromPublicSequenceApi(string $method): void
    {
        self::assertNotContains($method, get_class_methods(Sequence::class));
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

    public function testFoldSupportsAMutableAccumulatorWithoutChangingItsIdentity(): void
    {
        /** @var \ArrayObject<int, int> $initial */
        $initial = new \ArrayObject();
        $result = Sequence::from([1, 2])->fold($initial, static function (
            \ArrayObject $state,
            int $value,
        ): \ArrayObject {
            $state[] = $value;

            return $state;
        });

        self::assertSame($initial, $result);
        self::assertSame([1, 2], $result->getArrayCopy());
    }

    public function testFoldSourceExceptionsKeepTheirIdentityAndLeaveTheSequenceConsumed(): void
    {
        $expected = new \RuntimeException('fold source failed');
        $source = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $sequence = Sequence::from($source());

        try {
            $sequence->fold(0, static fn(int $state, int $value): int => $state + $value);
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);
    }

    public function testTerminalCallbackAndSourceExceptionsKeepTheirIdentity(): void
    {
        $expected = new \RuntimeException('fold failed');
        $sequence = Sequence::of(1);
        $callback = static function () use ($expected): never {
            throw $expected;
        };

        try {
            $sequence->fold(0, $callback);
            self::fail('The callback exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->assertConsumed($sequence);

        $expected = new \RuntimeException('source failed');
        $source = static function () use ($expected): iterable {
            yield 'before';
            throw $expected;
        };
        $sequence = Sequence::from($source());

        try {
            $sequence->collect();
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
}

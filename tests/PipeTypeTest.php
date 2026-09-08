<?php

declare(strict_types=1);

namespace Itera\Tests;

use PHPStan\Testing\TypeInferenceTestCase;

use function Itera\Pipe\collect;
use function Itera\Pipe\drop;
use function Itera\Pipe\filter;
use function Itera\Pipe\flatMap;
use function Itera\Pipe\fold;
use function Itera\Pipe\getIterator;
use function Itera\Pipe\map;
use function Itera\Pipe\sequence;
use function Itera\Pipe\skipUntil;
use function Itera\Pipe\take;
use function Itera\Pipe\until;
use function PHPStan\Testing\assertType;

final class PipeTypeTest extends TypeInferenceTestCase
{
    public function testInferredTypesMatchTheDeclaredExpectations(): void
    {
        foreach (self::gatherAssertTypes(__FILE__) as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    public function testIterableElementTypeFlowsThroughMapAndCollect(): void
    {
        /** @var iterable<int> $values */
        $values = [1, 2];
        $result = $values |> sequence() |> map(self::stringify(...)) |> collect();

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<string>', $result);
        }

        self::assertSame(['value:1', 'value:2'], $result->values());
    }

    public function testEveryIntermediateAndTerminalTransitionKeepsItsExactType(): void
    {
        /** @var iterable<int> $values */
        $values = [1, 2];
        $mapped = $values |> sequence() |> map(self::stringify(...));
        $filtered = $mapped |> filter(static fn(string $value): bool => $value !== '');
        $expanded = $filtered |> flatMap(self::parse(...));
        $bounded = $expanded
            |> until(static fn(float $value): bool => $value === 2.0)
            |> skipUntil(static fn(float $value): bool => $value >= 1.0)
            |> drop(0)
            |> take(2);
        $collection = $bounded |> collect();

        /** @var iterable<int> $foldValues */
        $foldValues = [1, 2];
        $folded = $foldValues
            |> sequence()
            |> fold('', static fn(string $state, int $value): string => $state . $value);

        /** @var iterable<string> $iteratorValues */
        $iteratorValues = ['a', 'b'];
        $iterator = $iteratorValues |> sequence() |> getIterator();

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<string>', $mapped);
            assertType('Itera\\Sequence<string>', $filtered);
            assertType('Itera\\Sequence<float>', $expanded);
            assertType('Itera\\Sequence<float>', $bounded);
            assertType('Itera\\Collection<float>', $collection);
            assertType('string', $folded);
            assertType('Traversable<int, string>', $iterator);
        }

        self::assertSame([1.0, 2.0], $collection->values());
        self::assertSame('12', $folded);
        self::assertSame(['a', 'b'], iterator_to_array($iterator));
    }

    public function testSavedPolymorphicFactoriesInferEachPipeInputIndependently(): void
    {
        $makeSequence = sequence();
        $takeOne = take(1);
        $dropNone = drop(0);
        $materialize = collect();
        $iterate = getIterator();

        /** @var iterable<int> $integers */
        $integers = [1, 2];
        /** @var iterable<string> $strings */
        $strings = ['a', 'b'];

        $integerSequence = $integers |> $makeSequence |> $dropNone |> $takeOne;
        $stringSequence = $strings |> $makeSequence |> $dropNone |> $takeOne;
        $integerCollection = $integerSequence |> $materialize;
        $stringCollection = $stringSequence |> $materialize;

        /** @var iterable<int> $moreIntegers */
        $moreIntegers = [3];
        /** @var iterable<string> $moreStrings */
        $moreStrings = ['c'];
        $integerIterator = $moreIntegers |> $makeSequence |> $iterate;
        $stringIterator = $moreStrings |> $makeSequence |> $iterate;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $integerCollection);
            assertType('Itera\\Collection<string>', $stringCollection);
            assertType('Traversable<int, int>', $integerIterator);
            assertType('Traversable<int, string>', $stringIterator);
        }

        self::assertSame([1], $integerCollection->values());
        self::assertSame(['a'], $stringCollection->values());
        self::assertSame([3], iterator_to_array($integerIterator));
        self::assertSame(['c'], iterator_to_array($stringIterator));
    }

    private static function stringify(int $value): string
    {
        return "value:{$value}";
    }

    /** @return iterable<float> */
    private static function parse(string $value): iterable
    {
        yield (float) substr($value, offset: 6);
    }
}

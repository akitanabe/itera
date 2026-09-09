<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Itera\Aggregator\any as anyWith;
use function Itera\Aggregator\associate as associateWith;
use function Itera\Aggregator\collect as collectWith;
use function Itera\Aggregator\combine as combineWith;
use function Itera\Aggregator\count as countWith;
use function Itera\Pipe\aggregate;
use function Itera\Pipe\associate;
use function Itera\Pipe\collect;
use function Itera\Pipe\drop;
use function Itera\Pipe\filter;
use function Itera\Pipe\flatMap;
use function Itera\Pipe\fold;
use function Itera\Pipe\getIterator;
use function Itera\Pipe\map;
use function Itera\Pipe\scan;
use function Itera\Pipe\sequence;
use function Itera\Pipe\skipUntil;
use function Itera\Pipe\take;
use function Itera\Pipe\tap;
use function Itera\Pipe\until;
use function PHPStan\Testing\assertType;

/** @mago-expect lint:too-many-methods */
final class PipeTypeTest extends TypeInferenceTestCase
{
    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return array_values([...parent::getAdditionalConfigFiles(), __DIR__ . '/../phpstan-extension.neon']);
    }

    #[RunInSeparateProcess]
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

    public function testScanInfersTheStateTypeThroughDirectAndSavedPipeClosures(): void
    {
        /** @var iterable<int> $values */
        $values = [1, 2];
        $direct = $values |> sequence() |> scan('', static fn(string $state, int $value): string => $state . $value);

        $saved = scan(0.0, static fn(float $state, int $value): float => $state + $value);
        /** @var iterable<int> $firstValues */
        $firstValues = [1, 2];
        /** @var iterable<int> $secondValues */
        $secondValues = [3];
        $first = $firstValues |> sequence() |> $saved;
        $second = $secondValues |> sequence() |> $saved;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<string>', $direct);
            assertType('Itera\\Sequence<float>', $first);
            assertType('Itera\\Sequence<float>', $second);
        }

        self::assertSame(['1', '12'], $direct->collect()->values());
        self::assertSame([1.0, 3.0], $first->collect()->values());
        self::assertSame([3.0], $second->collect()->values());
    }

    public function testTapPreservesTheElementTypeThroughDirectAndSavedPipeClosures(): void
    {
        /** @var iterable<int> $values */
        $values = [1, 2];
        $direct = $values |> sequence() |> tap(static function (int $value): void {});

        $saved = tap(static function (string $value): void {});
        /** @var iterable<string> $strings */
        $strings = ['a', 'b'];
        $applied = $strings |> sequence() |> $saved;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Sequence<int>', $direct);
            assertType('Itera\\Sequence<string>', $applied);
        }

        self::assertSame([1, 2], $direct->collect()->values());
        self::assertSame(['a', 'b'], $applied->collect()->values());
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

    public function testAggregateAndAssociatePreserveTerminalResultTypes(): void
    {
        $user = new PipeTypeUser(1, true);
        /** @var iterable<int> $values */
        $values = [1, 2];
        $collection = $values |> sequence() |> aggregate(collectWith());
        $map = [$user] |> sequence() |> aggregate(associateWith(static fn($value) => $value->id));
        $matched = [$user] |> sequence() |> aggregate(anyWith(static fn($value) => $value->active));
        $count = [$user] |> sequence() |> aggregate(countWith());
        $associated = [$user] |> sequence() |> associate(static fn($value) => $value->id);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $collection);
            assertType('Itera\\Map<int, Itera\\Tests\\PipeTypeUser>', $map);
            assertType('bool', $matched);
            assertType('int', $count);
            assertType('Itera\\Map<int, Itera\\Tests\\PipeTypeUser>', $associated);
        }

        self::assertSame([1, 2], $collection->values());
    }

    public function testCustomAggregatorPreservesItsTypeThroughPipeAggregate(): void
    {
        $definition = Aggregator::custom(static fn() => new AggregatorCustomExecution());
        $result = [1, 2] |> sequence() |> aggregate($definition);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('string', $result);
        }

        self::assertSame('1,2', $result);
    }

    public function testSavedAggregateClosuresPreserveDefinitionsAndPolymorphicCollect(): void
    {
        $collect = collectWith();
        $alias = $collect;
        $materialize = aggregate($collect);
        $materializeAlias = aggregate($alias);
        $hasPositive = aggregate(anyWith(static fn(int $value): bool => $value > 0));
        $byValue = associate(static fn(int $value): string => (string) $value);

        $integers = Sequence::from([1]) |> $materialize;
        $strings = Sequence::from(['value']) |> $materializeAlias;
        $matched = Sequence::from([1]) |> $hasPositive;
        $map = Sequence::from([1]) |> $byValue;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $integers);
            assertType('Itera\\Collection<string>', $strings);
            assertType('bool', $matched);
            assertType('Itera\\Map<string, int>', $map);
        }

        self::assertTrue($matched);
    }

    public function testCombinedAggregationKeepsItsShapeThroughDirectAndSavedPipeClosures(): void
    {
        $definition = combineWith(
            count: countWith(),
            items: collectWith(),
            positive: anyWith(static fn(int $value): bool => $value > 0),
        );
        $saved = aggregate($definition);
        $direct = Sequence::from([1]) |> aggregate($definition);
        $savedResult = Sequence::from([2]) |> $saved;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array{count: int, items: Itera\\Collection<int>, positive: bool}', $direct);
            assertType('array{count: int, items: Itera\\Collection<int>, positive: bool}', $savedResult);
        }

        self::assertSame(1, $direct['count']);
        self::assertSame([2], $savedResult['items']->values());
    }

    public function testSavedCombinedCollectClosureSpecializesForEachPipeInput(): void
    {
        $saved = aggregate(combineWith(count: countWith(), items: collectWith()));
        $integers = Sequence::from([1]) |> $saved;
        $strings = Sequence::from(['value']) |> $saved;

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array{count: int, items: Itera\\Collection<int>}', $integers);
            assertType('array{count: int, items: Itera\\Collection<string>}', $strings);
        }

        self::assertSame([1], $integers['items']->values());
        self::assertSame(['value'], $strings['items']->values());
    }

    public function testInlineCombinedPipeInfersChildCallbackInputAndResultShape(): void
    {
        $user = new PipeTypeUser(1, true);
        $result = Sequence::from([$user])
            |> aggregate(combineWith(
                count: countWith(),
                active: anyWith(static fn($value) => $value->active),
                items: collectWith(),
                byId: associateWith(static fn($value) => $value->id),
            ));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType(
                'array{count: int, active: bool, items: Itera\\Collection<Itera\\Tests\\PipeTypeUser>, byId: Itera\\Map<int, Itera\\Tests\\PipeTypeUser>}',
                $result,
            );
        }

        self::assertTrue($result['active']);
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

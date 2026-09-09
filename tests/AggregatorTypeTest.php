<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\Map;
use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Itera\Aggregator\all;
use function Itera\Aggregator\any;
use function Itera\Aggregator\associate;
use function Itera\Aggregator\collect;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\combine as combineAlias;
use function Itera\Aggregator\count;
use function Itera\Aggregator\filtering;
use function Itera\Aggregator\flatMapping;
use function Itera\Aggregator\folding;
use function Itera\Aggregator\mapping;
use function Itera\Aggregator\mapping as mappingAlias;
use function Itera\Aggregator\scanning;
use function PHPStan\Testing\assertType;

/** @mago-expect lint:too-many-methods */
final class AggregatorTypeTest extends TypeInferenceTestCase
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

    public function testNestedFactoriesReceiveTheSequenceElementType(): void
    {
        $collection = Sequence::from([new AggregatorTypeUser(1, true)])->aggregate(collect());
        $map = Sequence::from([new AggregatorTypeUser(1, true)])->aggregate(associate(static fn($user) => $user->id));
        $matched = Sequence::from([new AggregatorTypeUser(1, true)])->aggregate(any(static fn($user) => $user->active));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<Itera\\Tests\\AggregatorTypeUser>', $collection);
            assertType('Itera\\Map<int, Itera\\Tests\\AggregatorTypeUser>', $map);
            assertType('bool', $matched);
        }

        self::assertTrue($matched);
    }

    public function testTransformingFactoriesInferCallbackInputsAndResults(): void
    {
        $users = [new AggregatorTypeUser(1, true)];
        $mapped = Sequence::from($users)->aggregate(mapping(static fn($user) => $user->id));
        $aliased = Sequence::from($users)->aggregate(mappingAlias(static fn($user) => (string) $user->id));
        $filtered = Sequence::from($users)->aggregate(filtering(static fn($user) => $user->active));
        $flattened = Sequence::from($users)->aggregate(flatMapping(static fn($user) => [$user->id]));
        $scanned = Sequence::from($users)->aggregate(scanning(0, static fn($state, $user) => $state + $user->id));
        $folded = Sequence::from($users)->aggregate(folding('', static fn($state, $user) => $state . $user->id));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $mapped);
            assertType('Itera\\Collection<decimal-int-string>', $aliased);
            assertType('Itera\\Collection<Itera\\Tests\\AggregatorTypeUser>', $filtered);
            assertType('Itera\\Collection<int>', $flattened);
            assertType('Itera\\Collection<int>', $scanned);
            assertType('string', $folded);
        }

        self::assertSame([1], $mapped->values());
    }

    /**
     * @mago-expect lint:prefer-arrow-function
     * @mago-expect lint:inline-variable-return
     */
    public function testTransformingFactoriesInferOrdinaryClosureResults(): void
    {
        $users = [new AggregatorTypeUser(1, true)];
        $mapped = Sequence::from($users)->aggregate(mapping(static function ($user) {
            return $user->id;
        }));
        $flattened = Sequence::from($users)->aggregate(flatMapping(static function ($user) {
            return [$user->id];
        }));
        $mappedThroughLocal = Sequence::from($users)->aggregate(mapping(static function ($user) {
            $id = $user->id;

            return $id;
        }));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $mapped);
            assertType('Itera\\Collection<int>', $flattened);
            assertType('Itera\\Collection<int>', $mappedThroughLocal);
        }

        self::assertSame([1], $mapped->values());
        self::assertSame([1], $flattened->values());
        self::assertSame([1], $mappedThroughLocal->values());
    }

    public function testSavedTransformingDefinitionsAndCombinedBranchesKeepTheirTypes(): void
    {
        $mapping = mapping(static fn(AggregatorTypeUser $user): int => $user->id);
        $filtering = filtering(static fn(AggregatorTypeUser $user): bool => $user->active);
        $flatMapping = flatMapping(static fn(AggregatorTypeUser $user): iterable => [$user->id]);
        $scanning = scanning(0, static fn(int $state, AggregatorTypeUser $user): int => $state + $user->id);
        $folding = folding('', static fn(string $state, AggregatorTypeUser $user): string => $state . $user->id);
        $result = Sequence::from([new AggregatorTypeUser(1, true)])->aggregate(combine(
            mapped: $mapping,
            filtered: $filtering,
            flattened: $flatMapping,
            scanned: $scanning,
            folded: $folding,
        ));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Aggregator<Itera\\Tests\\AggregatorTypeUser, Itera\\Collection<int>>', $mapping);
            assertType(
                'array{mapped: Itera\\Collection<int>, filtered: Itera\\Collection<Itera\\Tests\\AggregatorTypeUser>, flattened: Itera\\Collection<int>, scanned: Itera\\Collection<int>, folded: string}',
                $result,
            );
        }

        self::assertSame('1', $result['folded']);
    }

    public function testCombinedChildrenPreserveNamesAndHeterogeneousResults(): void
    {
        $user = new AggregatorTypeUser(1, true);
        $result = Sequence::from([$user])->aggregate(combine(
            count: count(),
            anyMatch: any(static fn($value) => $value->active),
            items: collect(),
            byId: associate(static fn($value) => $value->id),
        ));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType(
                'array{count: int, anyMatch: bool, items: Itera\\Collection<Itera\\Tests\\AggregatorTypeUser>, byId: Itera\\Map<int, Itera\\Tests\\AggregatorTypeUser>}',
                $result,
            );
        }

        self::assertSame(1, $result['count']);
    }

    public function testSavedCombinedDefinitionSpecializesOnlyCollectForEachSequence(): void
    {
        $definition = combine(count: count(), items: collect());
        $alias = $definition;
        $integers = Sequence::from([1])->aggregate($definition);
        $strings = Sequence::from(['value'])->aggregate($alias);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array{count: int, items: Itera\\Collection<int>}', $integers);
            assertType('array{count: int, items: Itera\\Collection<string>}', $strings);
        }

        self::assertSame([1], $integers['items']->values());
        self::assertSame(['value'], $strings['items']->values());
    }

    public function testCombinedDefinitionSupportsTypedChildrenFlatMapAndConstantUnpack(): void
    {
        $children = [
            'matched' => any(static fn(int $value): bool => $value > 0),
            'byValue' => associate(static fn(int $value): string => (string) $value),
        ];
        $result = Sequence::from(['1'])->flatMap(static fn(string $value): iterable => [
            (int) $value,
        ])->aggregate(combine(...$children));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array{matched: bool, byValue: Itera\\Map<string, int>}', $result);
        }

        self::assertInstanceOf(Map::class, $result['byValue']);
    }

    public function testDynamicAndOptionalUnpackUseSafeArrayShapes(): void
    {
        $dynamic = Sequence::from([1])->aggregate(combine(...self::dynamicChildren()));
        $optional = Sequence::from([1])->aggregate(combine(...self::optionalChildren()));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array<string, bool>', $dynamic);
            assertType('array{a: bool, b?: int}', $optional);
        }

        self::assertTrue($dynamic['positive']);
        self::assertTrue($optional['a']);
    }

    public function testDynamicUnpackMixedWithFixedChildrenKeepsAllPossibleResults(): void
    {
        $dynamicFirst = Sequence::from([1])->aggregate(combine(...self::dynamicChildren(), total: count()));
        $dynamicLast = Sequence::from([1])->aggregate(combine(...self::fixedChildren(), ...self::dynamicChildren()));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array<string, bool|int>', $dynamicFirst);
            assertType('array<string, bool|int>', $dynamicLast);
        }

        self::assertSame(1, $dynamicFirst['total']);
        self::assertTrue($dynamicLast['positive']);
    }

    public function testImportedCombineAliasInfersChildCallbackInputType(): void
    {
        $result = Sequence::from([new AggregatorTypeUser(
            1,
            true,
        )])->aggregate(combineAlias(active: any(static fn($user): bool => $user->active)));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('array{active: bool}', $result);
        }

        self::assertTrue($result['active']);
    }

    public function testSavedCollectDefinitionSpecializesForEachSequence(): void
    {
        $collect = collect();
        $alias = $collect;
        $integers = Sequence::from([1])->aggregate($collect);
        $strings = Sequence::from(['value'])->aggregate($alias);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Collection<int>', $integers);
            assertType('Itera\\Collection<string>', $strings);
        }

        self::assertSame([1], $integers->values());
        self::assertSame(['value'], $strings->values());
    }

    public function testFlatMapResultsAndSavedTypedDefinitionsKeepTheirTypes(): void
    {
        $hasPositive = any(static fn(int $value): bool => $value > 0);
        $byValue = associate(static fn(int $value): string => (string) $value);
        $sequence = Sequence::from(['1'])->flatMap(static fn(string $value): iterable => [(int) $value]);
        $matched = $sequence->aggregate($hasPositive);
        $map = Sequence::from([1])->aggregate($byValue);
        $count = Sequence::from([1])->aggregate(count());
        $allPositive = Sequence::from([1])->aggregate(all(static fn(int $value): bool => $value > 0));

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Aggregator<int, bool>', $hasPositive);
            assertType('Itera\\Aggregator<int, Itera\\Map<string, int>>', $byValue);
            assertType('bool', $matched);
            assertType('Itera\\Map<string, int>', $map);
            assertType('int', $count);
            assertType('bool', $allPositive);
        }

        self::assertTrue($matched);
    }

    public function testAggregatorVarianceAllowsWiderInputAndNarrowerResult(): void
    {
        $wideInput = any(static fn(object $value): bool => $value instanceof AggregatorTypeUser);
        $accepted = self::acceptsUserAggregator($wideInput);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Aggregator<object, bool>', $wideInput);
            assertType('Itera\\Aggregator<Itera\\Tests\\AggregatorTypeUser, mixed>', $accepted);
        }

        self::assertTrue(Sequence::from([new AggregatorTypeUser(1, true)])->aggregate($accepted));
    }

    public function testCustomFactoryInfersItsExecutionInputAndResultTypes(): void
    {
        $definition = Aggregator::custom(static fn() => new AggregatorCustomExecution());
        $alias = $definition;
        $result = Sequence::from([1, 2])->aggregate($definition);
        $combined = Sequence::from([1])->aggregate(combine(custom: $alias, total: count()));
        $wideInput = Aggregator::custom(static fn() => new WideCustomExecution());
        $acceptedWideInput = self::acceptsUserAggregator($wideInput);

        if (function_exists('PHPStan\\Testing\\assertType')) {
            assertType('Itera\\Aggregator<int, string>', $definition);
            assertType('Itera\\Aggregator<int, string>', $alias);
            assertType('string', $result);
            assertType('array{custom: string, total: int}', $combined);
            assertType('Itera\\Aggregator<Itera\\Tests\\AggregatorTypeUser, mixed>', $acceptedWideInput);
        }

        self::assertSame('1,2', $result);
        self::assertSame('1', $combined['custom']);
        self::assertTrue(Sequence::from([new AggregatorTypeUser(1, true)])->aggregate($acceptedWideInput));
    }

    /**
     * @param Aggregator<AggregatorTypeUser, mixed> $aggregator
     * @return Aggregator<AggregatorTypeUser, mixed>
     */
    private static function acceptsUserAggregator(Aggregator $aggregator): Aggregator
    {
        return $aggregator;
    }

    /** @return array<string, Aggregator<int, bool>> */
    private static function dynamicChildren(): array
    {
        return ['positive' => any(static fn(int $value): bool => $value > 0)];
    }

    /** @return array<string, Aggregator<mixed, int>> */
    private static function fixedChildren(): array
    {
        return ['total' => count()];
    }

    /** @return array{a: Aggregator<int, bool>, b?: Aggregator<int, int>} */
    private static function optionalChildren(): array
    {
        return ['a' => any(static fn(int $value): bool => $value > 0)];
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\Sequence;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function Itera\Aggregator\all;
use function Itera\Aggregator\any;
use function Itera\Aggregator\associate;
use function Itera\Aggregator\collect;
use function Itera\Aggregator\count;
use function PHPStan\Testing\assertType;

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

    /**
     * @param Aggregator<AggregatorTypeUser, mixed> $aggregator
     * @return Aggregator<AggregatorTypeUser, mixed>
     */
    private static function acceptsUserAggregator(Aggregator $aggregator): Aggregator
    {
        return $aggregator;
    }
}

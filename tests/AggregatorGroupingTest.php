<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\combine;
use function Itera\Aggregator\countBy;
use function Itera\Aggregator\groupBy;
use function Itera\Aggregator\partition;
use function Itera\Aggregator\unique;

final class AggregatorGroupingTest extends TestCase
{
    public function testUniqueUsesStrictPhpMembershipAndPreservesFirstAppearanceOrder(): void
    {
        $shared = new \stdClass();
        $equalButDifferent = new \stdClass();
        $values = [1, '1', 1.0, 1, $shared, $shared, $equalButDifferent, [1], [1], NAN, NAN];

        $result = Sequence::from($values)->aggregate(unique())->values();

        self::assertCount(8, $result);
        self::assertSame([1, '1', 1.0, $shared, $equalButDifferent, [1]], array_slice($result, offset: 0, length: 6));
        self::assertTrue(is_float($result[6]) && is_nan($result[6]));
        self::assertTrue(is_float($result[7]) && is_nan($result[7]));
    }

    public function testGroupByPreservesGroupAndValueOrderUsingPhpArrayKeys(): void
    {
        $result = Sequence::from([
            'first',
            'second',
            'third',
        ])->aggregate(groupBy(static fn(string $value): int|string => match ($value) {
            'first' => '1',
            'second' => 1,
            default => 'other',
        }));

        self::assertSame([1, 'other'], $result->keys()->values());
        self::assertSame(['first', 'second'], $result->get(1)?->values());
        self::assertSame(['third'], $result->get('other')?->values());
    }

    public function testCountByReturnsCountsUsingPhpArrayKeys(): void
    {
        $result = Sequence::from([
            'first',
            'second',
            'third',
        ])->aggregate(countBy(static fn(string $value): int|string => match ($value) {
            'first' => '1',
            'second' => 1,
            default => 'other',
        }));

        self::assertSame([1 => 2, 'other' => 1], $result->raw());
    }

    public function testPartitionUsesPhpTruthinessAndPreservesOrderInBothSides(): void
    {
        $result = Sequence::from([0, '0', 1, 'yes', ''])->aggregate(partition(
            // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
            static fn(mixed $value): mixed => $value,
        ));

        self::assertSame([1, 'yes'], $result['matched']->values());
        self::assertSame([0, '0', ''], $result['unmatched']->values());
    }

    public function testCountByReleasesEarlierValuesBeforeSourceExhaustion(): void
    {
        $releasedBeforeEnd = false;
        $source = static function () use (&$releasedBeforeEnd): iterable {
            $first = new \stdClass();
            $reference = \WeakReference::create($first);
            yield $first;
            unset($first);
            yield new \stdClass();
            yield new \stdClass();
            $releasedBeforeEnd = $reference->get() === null;
            yield new \stdClass();
        };

        $result = Sequence::from($source())->aggregate(countBy(static fn(\stdClass $value): string => 'all'));

        self::assertSame(['all' => 4], $result->raw());
        self::assertTrue($releasedBeforeEnd);
    }

    public function testGroupingDefinitionsKeepNonEmptyRunsAndRepeatedBranchesIndependent(): void
    {
        $groups = groupBy(static fn(int $value): int => $value % 2);
        $partitions = partition(static fn(int $value): bool => ($value % 2) === 1);
        $counts = countBy(static fn(int $value): int => $value % 2);
        $definition = combine(
            groups: $groups,
            repeatedGroups: $groups,
            partitions: $partitions,
            repeatedPartitions: $partitions,
            counts: $counts,
            repeatedCounts: $counts,
        );

        $first = Sequence::from([1, 2])->aggregate($definition);
        $second = Sequence::from([3])->aggregate($definition);

        foreach (['groups', 'repeatedGroups'] as $name) {
            self::assertSame([1, 0], $first[$name]->keys()->values());
            self::assertSame([1], $first[$name]->get(1)?->values());
            self::assertSame([2], $first[$name]->get(0)?->values());
            self::assertSame([1], $second[$name]->keys()->values());
            self::assertSame([3], $second[$name]->get(1)?->values());
        }
        foreach (['partitions', 'repeatedPartitions'] as $name) {
            self::assertSame([1], $first[$name]['matched']->values());
            self::assertSame([2], $first[$name]['unmatched']->values());
            self::assertSame([3], $second[$name]['matched']->values());
            self::assertSame([], $second[$name]['unmatched']->values());
        }
        foreach (['counts', 'repeatedCounts'] as $name) {
            self::assertSame([1 => 1, 0 => 1], $first[$name]->raw());
            self::assertSame([1 => 1], $second[$name]->raw());
        }
        self::assertNotSame($first['groups'], $first['repeatedGroups']);
        self::assertNotSame($first['groups']->get(1), $first['repeatedGroups']->get(1));
        self::assertNotSame($first['partitions']['matched'], $first['repeatedPartitions']['matched']);
        self::assertNotSame($first['partitions']['unmatched'], $first['repeatedPartitions']['unmatched']);
        self::assertNotSame($first['counts'], $first['repeatedCounts']);
    }

    public function testDefinitionsProduceFreshEmptyResultsAndIndependentCombinedBranches(): void
    {
        $unique = unique();
        $grouped = groupBy(static fn(int $value): int => $value % 2);
        $partitioned = partition(static fn(int $value): bool => $value > 1);
        $counted = countBy(static fn(int $value): int => $value % 2);

        $firstEmptyUnique = Sequence::empty()->aggregate($unique);
        $secondEmptyUnique = Sequence::empty()->aggregate($unique);
        $firstEmptyGroup = Sequence::empty()->aggregate($grouped);
        $secondEmptyGroup = Sequence::empty()->aggregate($grouped);
        $emptyPartition = Sequence::empty()->aggregate($partitioned);
        $emptyCounts = Sequence::empty()->aggregate($counted);

        self::assertSame([], $firstEmptyUnique->values());
        self::assertNotSame($firstEmptyUnique, $secondEmptyUnique);
        self::assertSame([], $firstEmptyGroup->raw());
        self::assertNotSame($firstEmptyGroup, $secondEmptyGroup);
        self::assertSame([], $emptyPartition['matched']->values());
        self::assertSame([], $emptyPartition['unmatched']->values());
        self::assertNotSame($emptyPartition['matched'], $emptyPartition['unmatched']);
        self::assertSame([], $emptyCounts->raw());

        $source = static function (): iterable {
            yield 1;
            yield 1;
            yield 2;
            yield 3;
        };
        $combined = Sequence::from($source())->aggregate(combine(
            first: $unique,
            second: $unique,
            groups: $grouped,
            partition: $partitioned,
            counts: $counted,
        ));

        self::assertSame([1, 2, 3], $combined['first']->values());
        self::assertSame([1, 2, 3], $combined['second']->values());
        self::assertNotSame($combined['first'], $combined['second']);
        self::assertSame([1, 1, 3], $combined['groups']->get(1)?->values());
        self::assertSame([2], $combined['groups']->get(0)?->values());
        self::assertSame([2, 3], $combined['partition']['matched']->values());
        self::assertSame([1, 1], $combined['partition']['unmatched']->values());
        self::assertSame([1 => 3, 0 => 1], $combined['counts']->raw());
        self::assertSame([4], Sequence::from([4, 4])->aggregate($unique)->values());
    }

    public function testCallbackExceptionKeepsIdentityAndConsumesTheSequence(): void
    {
        $expected = new \RuntimeException('selector failed');
        $sequence = Sequence::from([1]);

        try {
            $sequence->aggregate(groupBy(static function () use ($expected): never {
                throw $expected;
            }));
            self::fail('The expected exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->collect();
    }
}

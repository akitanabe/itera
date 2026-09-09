<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\average;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\find;
use function Itera\Aggregator\first;
use function Itera\Aggregator\join;
use function Itera\Aggregator\max;
use function Itera\Aggregator\min;
use function Itera\Aggregator\sum;

final class AggregatorNumericSearchTest extends TestCase
{
    public function testNumericAggregatorsUseTheirDocumentedEmptyResultsAndPhpNumericOperations(): void
    {
        self::assertSame(0, Sequence::empty()->aggregate(sum()));
        self::assertNull(Sequence::empty()->aggregate(min()));
        self::assertNull(Sequence::empty()->aggregate(max()));
        self::assertNull(Sequence::empty()->aggregate(average()));

        self::assertSame(3.0, Sequence::from([1, 2.5, -0.5])->aggregate(sum()));
        self::assertSame(6, Sequence::from([1, 2, 3])->aggregate(sum()));
        self::assertSame(1.0, Sequence::from([1.0, 1, 2])->aggregate(min()));
        self::assertSame(2.0, Sequence::from([2.0, 2, 1])->aggregate(max()));
        self::assertSame(-3, Sequence::from([4, -3, 2])->aggregate(min()));
        self::assertSame(4, Sequence::from([-3, 4, 2])->aggregate(max()));
        self::assertSame(2.0, Sequence::from([1, 2, 3])->aggregate(average()));
        self::assertIsFloat(Sequence::from([PHP_INT_MAX, 1])->aggregate(sum()));

        $nanMinimum = Sequence::from([NAN, 1.0])->aggregate(min());
        $nanMaximum = Sequence::from([NAN, 1.0])->aggregate(max());
        $infiniteAverage = Sequence::from([INF, 1.0])->aggregate(average());
        self::assertIsFloat($nanMinimum);
        self::assertIsFloat($nanMaximum);
        self::assertIsFloat($infiniteAverage);
        self::assertTrue(is_nan($nanMinimum));
        self::assertTrue(is_nan($nanMaximum));
        self::assertTrue(is_infinite($infiniteAverage));
    }

    public function testFindUsesPhpTruthinessAndStopsAtTheFirstMatchingValue(): void
    {
        $source = static function (): iterable {
            yield '0';
            yield 'matched';
            throw new \RuntimeException('source advanced after find decided');
        };
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $definition = find(static fn(string $value): string => $value === 'matched' ? $value : '0');

        self::assertSame('matched', Sequence::from($source())->aggregate($definition));
        self::assertNull(Sequence::from(['0', ''])->aggregate($definition));
        self::assertNull(Sequence::empty()->aggregate($definition));
    }

    public function testFirstStopsAtNullWithoutReadingAnotherSourceValue(): void
    {
        $source = static function (): iterable {
            yield null;
            throw new \RuntimeException('source advanced after first value');
        };

        $read = Sequence::from($source())->aggregate(first());
        /** @var iterable<mixed> $empty */
        $empty = [];
        $emptyResult = Sequence::from($empty)->aggregate(first());

        self::assertSame(['read' => null, 'empty' => null], ['read' => $read, 'empty' => $emptyResult]);
    }

    public function testJoinPlacesSeparatorsBetweenEveryElementIncludingEmptyStrings(): void
    {
        self::assertSame('', Sequence::empty()->aggregate(join('|')));
        self::assertSame('|a|', Sequence::from(['', 'a', ''])->aggregate(join('|')));
    }

    public function testCombinedEarlyAndFullBranchesShareOneTraversalAndFreshState(): void
    {
        $source = static function (): iterable {
            yield 1;
            yield 2;
            yield 3;
        };
        $first = first();
        $match = find(static fn(int $value): bool => $value === 2);
        $total = sum();
        $combined = combine(first: $first, match: $match, total: $total);

        self::assertSame(['first' => 1, 'match' => 2, 'total' => 6], Sequence::from($source())->aggregate($combined));
        self::assertSame(['first' => 4, 'match' => null, 'total' => 4], Sequence::from([4])->aggregate($combined));
        self::assertSame(5, Sequence::from([5, 6])->aggregate($first));
    }

    public function testCombinedEarlyBranchesStopWhenEveryResultIsKnown(): void
    {
        $source = static function (): iterable {
            yield 1;
            yield 2;
            throw new \RuntimeException('source advanced after all branches decided');
        };

        self::assertSame(
            ['first' => 1, 'match' => 2],
            Sequence::from($source())->aggregate(combine(
                first: first(),
                match: find(static fn(int $value): bool => $value === 2),
            )),
        );
    }
}

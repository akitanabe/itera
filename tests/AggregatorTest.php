<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\count;

final class AggregatorTest extends TestCase
{
    public function testCombineReturnsNamedResultsFromOneTraversal(): void
    {
        $mapped = [];

        $result = Sequence::from([1, 2, 3])->map(static function (int $value) use (&$mapped): int {
            $mapped[] = $value;

            return $value;
        })->aggregate(combine(total: count(), hasEven: any(static fn(int $value): bool => ($value % 2) === 0)));

        self::assertSame(['total' => 3, 'hasEven' => true], $result);
        self::assertSame([1, 2, 3], $mapped);
    }

    public function testCountReturnsTheNumberOfPipelineOutputsAndZeroForEmptyInput(): void
    {
        self::assertSame(
            4,
            Sequence::from([1, 2])->flatMap(static fn(int $value): iterable => [$value, -$value])->aggregate(count()),
        );
        self::assertSame(0, Sequence::empty()->aggregate(count()));
    }

    public function testOneDefinitionCanBeReusedWithoutSharingState(): void
    {
        $count = count();

        self::assertSame(2, Sequence::from(['first', 'second'])->aggregate($count));
        self::assertSame(1, Sequence::from(['only'])->aggregate($count));
    }
}

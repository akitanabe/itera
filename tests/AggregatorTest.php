<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\count;

final class AggregatorTest extends TestCase
{
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

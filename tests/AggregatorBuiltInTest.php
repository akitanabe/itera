<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Collection;
use Itera\Map;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\all;
use function Itera\Aggregator\any;
use function Itera\Aggregator\associate;
use function Itera\Aggregator\collect;
use function Itera\Aggregator\count;

final class AggregatorBuiltInTest extends TestCase
{
    public function testEmptyInputsHaveTheirIdentityResultsAndFreshMaterializations(): void
    {
        $calls = 0;
        $firstCollection = Sequence::empty()->aggregate(collect());
        $secondCollection = Sequence::empty()->aggregate(collect());
        $firstMap = Sequence::empty()->aggregate(associate(static function () use (&$calls): string {
            ++$calls;

            return 'unused';
        }));
        $secondMap = Sequence::empty()->aggregate(associate(static fn(): string => 'unused'));

        self::assertSame(0, Sequence::empty()->aggregate(count()));
        self::assertFalse(Sequence::empty()->aggregate(any(static function () use (&$calls): bool {
            ++$calls;

            return true;
        })));
        self::assertTrue(Sequence::empty()->aggregate(all(static function () use (&$calls): bool {
            ++$calls;

            return false;
        })));
        self::assertInstanceOf(Collection::class, $firstCollection);
        self::assertSame([], $firstCollection->values());
        self::assertNotSame($firstCollection, $secondCollection);
        self::assertInstanceOf(Map::class, $firstMap);
        self::assertSame([], $firstMap->raw());
        self::assertNotSame($firstMap, $secondMap);
        self::assertSame(0, $calls);
    }

    public function testAnyAndAllUsePhpTruthinessAndStopAtTheFirstDecision(): void
    {
        $truthyObject = new \stdClass();
        $anySeen = [];
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $any = any(static function (mixed $value) use (&$anySeen): mixed {
            $anySeen[] = $value;

            return $value;
        });
        $allSeen = [];
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $all = all(static function (mixed $value) use (&$allSeen): mixed {
            $allSeen[] = $value;

            return $value;
        });

        self::assertTrue(Sequence::from(['0', $truthyObject, 'not read'])->aggregate($any));
        self::assertSame(['0', $truthyObject], $anySeen);
        self::assertFalse(Sequence::from([$truthyObject, '0', 'not read'])->aggregate($all));
        self::assertSame([$truthyObject, '0'], $allSeen);
    }

    public function testAnyStopsOuterAndExpandedInputsWithoutAdvancingPastTheMatch(): void
    {
        $events = [];
        $seen = [];
        $outer = new SequenceRecordingIterator([1, 2], $events, 'outer');
        $inner = new SequenceRecordingIterator([10, 11, 12], $events, 'inner');
        $sequence = Sequence::from($outer)->flatMap(static fn(): iterable => $inner);

        self::assertTrue($sequence->aggregate(any(static function (mixed $value) use (&$seen): bool {
            $seen[] = $value;

            return $value === 11;
        })));
        self::assertSame([10, 11], $seen);
        self::assertSame(
            [
                'outer:valid:0',
                'outer:current:0',
                'inner:valid:0',
                'inner:current:0',
                'inner:next:0',
                'inner:valid:1',
                'inner:current:1',
            ],
            $events,
        );

        $this->expectException(SequenceConsumedException::class);
        $sequence->collect();
    }

    public function testAllStopsTheSourceWithoutAdvancingPastTheFirstFalsyResult(): void
    {
        $events = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $events, 'source');

        self::assertFalse(Sequence::from($source)->aggregate(all(
            static fn(mixed $value): bool => is_int($value) && $value < 2,
        )));
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $events,
        );
    }

    public function testCollectPreservesOutputOrderNullAndObjectIdentityWithoutInputKeys(): void
    {
        $object = new \stdClass();
        $values = ['named' => null, 8 => $object];
        $aggregated = Sequence::from($values)->aggregate(collect());
        $direct = Sequence::from($values)->collect();

        self::assertSame($direct->values(), $aggregated->values());
        self::assertSame([null, $object], $aggregated->values());
        self::assertSame($object, $aggregated->at(1));
    }

    public function testAssociateUsesPipelineOutputsAndTheLastValueForDuplicateKeys(): void
    {
        $first = new AggregatorBuiltInItem(1, 'first');
        $replacement = new AggregatorBuiltInItem(1, 'replacement');
        $second = new AggregatorBuiltInItem(2, 'second');
        $argumentCounts = [];
        $selector = static function (AggregatorBuiltInItem $item) use (&$argumentCounts): int {
            $argumentCounts[] = func_num_args();

            return $item->id;
        };
        $aggregated = Sequence::from(['first' => $first, 8 => $replacement, 9 => $second])->map(
            static fn(AggregatorBuiltInItem $item): AggregatorBuiltInItem => $item,
        )->aggregate(associate($selector));
        $direct = Sequence::from([$first, $replacement, $second])->associate($selector);

        self::assertSame($direct->raw(), $aggregated->raw());
        self::assertSame([1 => $replacement, 2 => $second], $aggregated->raw());
        self::assertSame($replacement, $aggregated->get(1));
        self::assertSame([1, 1, 1, 1, 1, 1], $argumentCounts);
    }
}

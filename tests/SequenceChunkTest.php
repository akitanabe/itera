<?php

declare(strict_types=1);

namespace Itera\Tests;

use InvalidArgumentException;
use Itera\Collection;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SequenceChunkTest extends TestCase
{
    /**
     * @param iterable<mixed> $source
     * @param list<list<mixed>> $expected
     */
    #[DataProvider('chunkCases')]
    public function testChunkMaterializesFreshOrderedCollections(iterable $source, int $size, array $expected): void
    {
        $chunks = Sequence::from($source)->chunk($size)->collect()->values();

        self::assertSame($expected, array_map(static fn(Collection $chunk): array => $chunk->values(), $chunks));
        foreach ($chunks as $chunk) {
            self::assertInstanceOf(Collection::class, $chunk);
        }
        if (count($chunks) > 1) {
            self::assertNotSame($chunks[0], $chunks[1]);
        }
    }

    /** @return iterable<string, array{iterable<mixed>, int, list<list<mixed>>}> */
    public static function chunkCases(): iterable
    {
        yield 'empty' => [[], 2, []];
        yield 'size one' => [[1, 2], 1, [[1], [2]]];
        yield 'exact' => [[1, 2, 3, 4], 2, [[1, 2], [3, 4]]];
        yield 'partial tail' => [[1, 2, 3], 2, [[1, 2], [3]]];
        yield 'oversize' => [[1, 2], 3, [[1, 2]]];
        yield 'keys discarded' => [new \ArrayIterator(['a' => 1, 9 => 2]), 2, [[1, 2]]];
        yield 'null and false retained' => [[null, false, 1], 2, [[null, false], [1]]];
    }

    public function testInvalidSizeLeavesAnUnconsumedPipelineIntact(): void
    {
        foreach ([0, -1] as $size) {
            $sequence = Sequence::from([1, 2])->map(static fn(int $value): int => $value * 10);

            try {
                $sequence->chunk($size);
                self::fail('Invalid chunk size was accepted.');
            } catch (InvalidArgumentException) {
                self::assertSame([10, 20], $sequence->collect()->values());
            }
        }
    }

    public function testConsumedCheckPrecedesSizeValidation(): void
    {
        $sequence = Sequence::of(1);
        $sequence->collect();

        $this->expectException(SequenceConsumedException::class);
        $sequence->chunk(0);
    }

    public function testChunkReturnsTheSameSequenceAndDefersReading(): void
    {
        $events = [];
        $source = static function () use (&$events): iterable {
            $events[] = 'read';
            yield 1;
        };
        $sequence = Sequence::from($source());

        self::assertSame($sequence, $sequence->chunk(2));
        self::assertSame([], $events);
        self::assertSame([[1]], self::values($sequence));
        self::assertSame(['read'], $events);
    }

    /**
     * @template T
     * @param Sequence<Collection<T>> $sequence
     * @return list<list<T>>
     */
    private static function values(Sequence $sequence): array
    {
        return array_map(static fn(Collection $chunk): array => $chunk->values(), $sequence->collect()->values());
    }
}

<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\combine;
use function Itera\Aggregator\filtering;
use function Itera\Aggregator\flatMapping;
use function Itera\Aggregator\folding;
use function Itera\Aggregator\mapping;
use function Itera\Aggregator\scanning;

final class AggregatorTransformingTest extends TestCase
{
    public function testMappingFilteringAndFlatMappingPreserveTheirOutputOrder(): void
    {
        $mapped = Sequence::from([1, 2, 3])->aggregate(mapping(static fn(int $value): string => "v{$value}"));
        // @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
        $filtered = Sequence::from([0, '0', 1, 'yes'])->aggregate(filtering(static fn(mixed $value): mixed => $value));
        $flattened = Sequence::from([2, 1])->aggregate(flatMapping(static function (int $value): iterable {
            yield 'ignored' => $value;
            yield 'also ignored' => -$value;
        }));

        self::assertSame(['v1', 'v2', 'v3'], $mapped->values());
        self::assertSame([1, 'yes'], $filtered->values());
        self::assertSame([2, -2, 1, -1], $flattened->values());
    }

    public function testTransformingAggregatorsReturnFreshEmptyCollections(): void
    {
        $definitions = [
            mapping(static fn(int $value): int => $value),
            filtering(static fn(int $value): bool => true),
            flatMapping(static fn(int $value): iterable => [$value]),
            scanning(0, static fn(int $state, int $value): int => $state + $value),
        ];

        foreach ($definitions as $definition) {
            $first = Sequence::empty()->aggregate($definition);
            $second = Sequence::empty()->aggregate($definition);

            self::assertSame([], $first->values());
            self::assertNotSame($first, $second);
        }
    }

    public function testScanningEmitsOnlyUpdatedStatesAndFoldingReturnsTheFinalState(): void
    {
        $seed = new AggregatorRunningTotal();
        $step = static function (AggregatorRunningTotal $state, int $value): AggregatorRunningTotal {
            $state->value += $value;

            return $state;
        };
        $states = Sequence::from([1, 2])->aggregate(scanning($seed, $step));

        self::assertSame([$seed, $seed], $states->values());
        self::assertSame(3, $seed->value);
        self::assertSame(
            '123',
            Sequence::from([1, 2, 3])->aggregate(folding(
                '',
                static fn(string $state, int $value): string => $state . $value,
            )),
        );
        self::assertSame(
            'seed',
            Sequence::empty()->aggregate(folding(
                'seed',
                static fn(string $state, int $value): string => $state . $value,
            )),
        );
    }

    public function testDefinitionsHaveFreshExecutionStateAcrossCombinedBranchesAndRuns(): void
    {
        $running = scanning(0, static fn(int $state, int $value): int => $state + $value);
        $mapped = mapping(static fn(int $value): int => $value * 10);
        $folded = folding(0, static fn(int $state, int $value): int => $state + $value);
        $sourceReads = 0;
        $source = static function () use (&$sourceReads): iterable {
            foreach ([1, 2, 3] as $value) {
                ++$sourceReads;
                yield $value;
            }
        };

        $combined = Sequence::from($source())->aggregate(combine(
            first: $running,
            second: $running,
            mapped: $mapped,
            folded: $folded,
        ));

        self::assertSame(3, $sourceReads);
        self::assertSame([1, 3, 6], $combined['first']->values());
        self::assertSame([1, 3, 6], $combined['second']->values());
        self::assertNotSame($combined['first'], $combined['second']);
        self::assertSame([10, 20, 30], $combined['mapped']->values());
        self::assertSame(6, $combined['folded']);
        self::assertSame([4], Sequence::from([4])->aggregate($running)->values());
        self::assertSame(4, Sequence::from([4])->aggregate($folded));
    }

    public function testMapperAndInnerIterableExceptionsKeepIdentityAndConsumeTheSequence(): void
    {
        foreach (['mapper', 'inner'] as $failure) {
            $expected = new \RuntimeException($failure);
            $sequence = Sequence::from([1]);
            $definition = $failure === 'mapper'
                ? mapping(static function () use ($expected): never {
                    throw $expected;
                })
                : flatMapping(static function () use ($expected): iterable {
                    yield 1;
                    throw $expected;
                });

            try {
                $sequence->aggregate($definition);
                self::fail('The expected exception was not thrown.');
            } catch (\RuntimeException $actual) {
                self::assertSame($expected, $actual);
            }

            try {
                $sequence->collect();
                self::fail('The failed sequence remained reusable.');
            } catch (SequenceConsumedException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

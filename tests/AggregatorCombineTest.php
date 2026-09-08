<?php

declare(strict_types=1);

namespace Itera\Tests;

use InvalidArgumentException;
use Itera\Aggregator;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Itera\Aggregator\all;
use function Itera\Aggregator\any;
use function Itera\Aggregator\associate;
use function Itera\Aggregator\collect;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\count;

/** @mago-expect lint:too-many-methods */
final class AggregatorCombineTest extends TestCase
{
    public function testHeterogeneousChildrenShareOneMappedTraversal(): void
    {
        $mapped = [];
        $first = new AggregatorTypeUser(1, false);
        $second = new AggregatorTypeUser(2, true);

        $result = Sequence::from([$first, $second])->map(static function (AggregatorTypeUser $user) use (
            &$mapped,
        ): AggregatorTypeUser {
            $mapped[] = $user->id;

            return $user;
        })->aggregate(combine(
            count: count(),
            active: any(static fn(AggregatorTypeUser $user): bool => $user->active),
            items: collect(),
            byId: associate(static fn(AggregatorTypeUser $user): int => $user->id),
        ));

        self::assertSame([1, 2], $mapped);
        self::assertSame(['count', 'active', 'items', 'byId'], array_keys($result));
        self::assertSame(2, $result['count']);
        self::assertTrue($result['active']);
        self::assertSame([$first, $second], $result['items']->values());
        self::assertSame([1 => $first, 2 => $second], $result['byId']->raw());
    }

    public function testEachChildStopsAfterItsOwnDecisionWhileOtherChildrenContinue(): void
    {
        $anyValues = [];
        $allValues = [];

        $result = Sequence::from([1, 2, 3])->aggregate(combine(
            any: any(static function (int $value) use (&$anyValues): bool {
                $anyValues[] = $value;

                return $value === 1;
            }),
            all: all(static function (int $value) use (&$allValues): bool {
                $allValues[] = $value;

                return $value < 2;
            }),
            count: count(),
        ));

        self::assertSame(['any' => true, 'all' => false, 'count' => 3], $result);
        self::assertSame([1], $anyValues);
        self::assertSame([1, 2], $allValues);
    }

    public function testAllChildrenCompletingStopsTheSourceAtTheDecidingValue(): void
    {
        $sourceEvents = [];
        $callbackEvents = [];
        $source = new SequenceRecordingIterator([1, 2, 3], $sourceEvents, 'source');

        $result = Sequence::from($source)->aggregate(combine(
            any: any(static function (mixed $value) use (&$callbackEvents): bool {
                if (!is_int($value)) {
                    throw new \LogicException('Expected an integer test value.');
                }
                $callbackEvents[] = 'any:' . $value;

                return $value === 1;
            }),
            all: all(static function (mixed $value) use (&$callbackEvents): bool {
                if (!is_int($value)) {
                    throw new \LogicException('Expected an integer test value.');
                }
                $callbackEvents[] = 'all:' . $value;

                return $value < 2;
            }),
        ));

        self::assertSame(['any' => true, 'all' => false], $result);
        self::assertSame(['any:1', 'all:1', 'all:2'], $callbackEvents);
        self::assertSame(
            ['source:valid:0', 'source:current:0', 'source:next:0', 'source:valid:1', 'source:current:1'],
            $sourceEvents,
        );
    }

    public function testAllChildrenCompletingInsideFlatMapAvoidsLaterExpansion(): void
    {
        $mappers = [];
        $result = Sequence::from([1, 2])->flatMap(static function (int $value) use (&$mappers): iterable {
            $mappers[] = $value;
            if ($value === 2) {
                throw new \RuntimeException('later outer value must not expand');
            }

            return [1, 2, 3];
        })->aggregate(combine(
            any: any(static fn(int $value): bool => $value === 1),
            all: all(static fn(int $value): bool => $value < 2),
        ));

        self::assertSame(['any' => true, 'all' => false], $result);
        self::assertSame([1], $mappers);
    }

    public function testChildrenRunInDeclarationOrderAndAFailureSkipsLaterChildrenForThatValue(): void
    {
        $expected = new \RuntimeException('first child failed');
        $events = [];
        $definition = combine(
            first: any(static function (int $value) use (&$events, $expected): bool {
                $events[] = 'first:' . $value;
                if ($value === 2) {
                    throw $expected;
                }

                return false;
            }),
            second: all(static function (int $value) use (&$events): bool {
                $events[] = 'second:' . $value;

                return true;
            }),
        );

        try {
            Sequence::from([1, 2, 3])->aggregate($definition);
            self::fail('The child exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(['first:1', 'second:1', 'first:2'], $events);
        self::assertSame(['first' => false, 'second' => true], Sequence::from([4])->aggregate($definition));
    }

    public function testEmptyInputAndOneChildKeepNamedResultShapeWithoutCallingCallbacks(): void
    {
        $calls = 0;
        $empty = Sequence::empty()->aggregate(combine(
            any: any(static function () use (&$calls): bool {
                ++$calls;

                return true;
            }),
            all: all(static function () use (&$calls): bool {
                ++$calls;

                return false;
            }),
        ));

        self::assertSame(['any' => false, 'all' => true], $empty);
        self::assertSame(0, $calls);
        self::assertSame(['total' => 2], Sequence::from([1, 2])->aggregate(combine(total: count())));
    }

    /** @return iterable<string, array{callable(): void}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'empty' => [static function (): void {
            combine();
        }];
        yield 'positional' => [static function (): void {
            combine(count());
        }];
        yield 'mixed positional and named' => [static function (): void {
            combine(count(), named: count());
        }];
        yield 'numeric string key after PHP conversion' => [static function (): void {
            combine(...['0' => count()]);
        }];
        yield 'nested through a variable' => [static function (): void {
            $nested = combine(child: count());
            combine(parent: $nested);
        }];
    }

    #[DataProvider('invalidDefinitions')]
    public function testInvalidStructuresAreRejectedWhenTheDefinitionIsCreated(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    public function testNestedRejectionDoesNotExecuteAChildCallback(): void
    {
        $calls = 0;
        $nested = combine(child: any(static function () use (&$calls): bool {
            ++$calls;

            return true;
        }));

        try {
            combine(parent: $nested);
            self::fail('The nested definition was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        self::assertSame(0, $calls);
    }

    public function testStringKeyUnpackIsAcceptedAndPreservesOrder(): void
    {
        $children = ['first' => count(), 'second' => any(static fn(int $value): bool => $value === 2)];

        self::assertSame(['first' => 2, 'second' => true], Sequence::from([1, 2])->aggregate(combine(...$children)));
    }

    public function testReusedDefinitionsAndRepeatedChildrenHaveIndependentMaterializedResults(): void
    {
        $child = collect();
        $definition = combine(first: $child, second: $child);
        $firstRun = Sequence::from([1, 2])->aggregate($definition);
        $secondRun = Sequence::from([3])->aggregate($definition);

        self::assertSame([1, 2], $firstRun['first']->values());
        self::assertSame([1, 2], $firstRun['second']->values());
        self::assertSame([3], $secondRun['first']->values());
        self::assertNotSame($firstRun['first'], $firstRun['second']);
        self::assertNotSame($firstRun['first'], $secondRun['first']);
    }

    public function testRepeatedAssociateChildrenAndRunsCreateIndependentMaps(): void
    {
        $child = associate(static fn(int $value): int => $value);
        $definition = combine(first: $child, second: $child);
        $firstRun = Sequence::from([1])->aggregate($definition);
        $secondRun = Sequence::from([2])->aggregate($definition);

        self::assertSame([1 => 1], $firstRun['first']->raw());
        self::assertSame([1 => 1], $firstRun['second']->raw());
        self::assertSame([2 => 2], $secondRun['first']->raw());
        self::assertNotSame($firstRun['first'], $firstRun['second']);
        self::assertNotSame($firstRun['first'], $secondRun['first']);
    }

    public function testCombinedDefinitionCanBeUsedReentrantlyWithoutSharingState(): void
    {
        $nestedResults = [];
        $definition = null;
        $definition = combine(count: count(), byValue: associate(static function (int $value) use (
            &$definition,
            &$nestedResults,
        ): int {
            if ($value === 1) {
                if (!$definition instanceof Aggregator) {
                    throw new \LogicException('The definition must exist before execution.');
                }
                $nestedResults[] = Sequence::from([2])->aggregate($definition);
            }

            return $value;
        }));

        $outer = Sequence::from([1, 3])->aggregate($definition);

        self::assertSame(2, $outer['count']);
        self::assertSame([1 => 1, 3 => 3], $outer['byValue']->raw());
        self::assertSame(1, $nestedResults[0]['count']);
        self::assertSame([2 => 2], $nestedResults[0]['byValue']->raw());
    }

    public function testFailureAfterOneChildCompletesKeepsIdentityConsumesSequenceAndLeavesDefinitionReusable(): void
    {
        $expected = new \RuntimeException('source failed after decision');
        $seen = [];
        $source = static function () use ($expected): iterable {
            yield 1;
            throw $expected;
        };
        $definition = combine(decided: any(static function (int $value) use (&$seen): bool {
            $seen[] = $value;

            return true;
        }), count: count());
        $sequence = Sequence::from($source());

        try {
            $sequence->aggregate($definition);
            self::fail('The source exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame([1], $seen);
        try {
            $sequence->getIterator();
            self::fail('The failed Sequence remained reusable.');
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);
        }
        self::assertSame(['decided' => true, 'count' => 1], Sequence::from([2])->aggregate($definition));
    }

    public function testLaterChildFailureDoesNotResumeAnAlreadyCompletedChild(): void
    {
        $expected = new \RuntimeException('later child failed');
        $firstSeen = [];
        $definition = combine(
            first: any(static function (int $value) use (&$firstSeen): bool {
                $firstSeen[] = $value;

                return true;
            }),
            later: any(static function (int $value) use ($expected): bool {
                if ($value === 2) {
                    throw $expected;
                }

                return false;
            }),
        );
        $sequence = Sequence::from([1, 2]);

        try {
            $sequence->aggregate($definition);
            self::fail('The child exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame([1], $firstSeen);
        try {
            $sequence->getIterator();
            self::fail('The failed Sequence remained reusable.');
        } catch (SequenceConsumedException) {
            $this->addToAssertionCount(1);
        }
        self::assertSame(['first' => true, 'later' => false], Sequence::from([3])->aggregate($definition));
    }
}

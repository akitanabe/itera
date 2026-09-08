<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Map;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\TestCase;

final class SequenceAssociationTest extends TestCase
{
    public function testAssociateConsumesTheLazyPipelineAndUsesPipelineOutputValues(): void
    {
        $events = [];
        $argumentCounts = [];
        $sequence = Sequence::from(
            (static function () use (&$events): iterable {
                $events[] = 'source';
                yield 'first';
                yield 'second';
            })(),
        )->map(static function (string $value) use (&$events): string {
            $events[] = 'map:' . $value;

            return strtoupper($value);
        });

        $map = $sequence->associate(static function (string $value) use (&$argumentCounts): string {
            $argumentCounts[] = func_num_args();

            return $value[0];
        });

        self::assertInstanceOf(Map::class, $map);
        self::assertSame(['F' => 'FIRST', 'S' => 'SECOND'], $map->raw());
        self::assertSame(['source', 'map:first', 'map:second'], $events);
        self::assertSame([1, 1], $argumentCounts);

        $this->expectException(SequenceConsumedException::class);
        $sequence->associate(static fn(string $value): string => $value);
    }

    public function testAssociateCallbackExceptionsKeepTheirIdentityAndPreventReuse(): void
    {
        $expected = new \RuntimeException('key selection failed');
        $sequence = Sequence::from(['value']);

        try {
            $sequence->associate(static function (string $value) use ($expected): never {
                throw $expected;
            });
            self::fail('The key selector exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->collect();
    }

    public function testAssociateUsesTheLastValueForDuplicateKeys(): void
    {
        $map = Sequence::of('first', 'replacement')->associate(static fn(): string => 'same');

        self::assertSame(['same' => 'replacement'], $map->raw());
    }
}

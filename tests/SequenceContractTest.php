<?php

declare(strict_types=1);

namespace Itera\Tests;

use Countable;
use Itera\Collection;
use Itera\Sequence;
use Itera\SequenceConsumedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SequenceContractTest extends TestCase
{
    public function testToCollectionAlwaysCreatesANewCollection(): void
    {
        $original = Collection::of('first', 'second');
        $materialized = Sequence::from($original)->toCollection();
        $emptyOriginal = Collection::empty();
        $emptyMaterialized = Sequence::from($emptyOriginal)->toCollection();

        self::assertNotSame($original, $materialized);
        self::assertSame($original->toArray(), $materialized->toArray());
        self::assertNotSame($emptyOriginal, $emptyMaterialized);
        self::assertSame([], $emptyMaterialized->toArray());
    }

    public function testBothCollectionSequenceEntrypointsHaveTheSameMeaning(): void
    {
        $collection = Collection::from(['named' => 'alpha', 8 => 'beta']);

        self::assertSame(['alpha', 'beta'], Sequence::from($collection)->toArray());
        self::assertSame(['alpha', 'beta'], $collection->sequence()->toArray());
    }

    #[DataProvider('instanceOperations')]
    public function testEveryInstanceOperationRejectsAConsumedSequence(callable $operation): void
    {
        $sequence = Sequence::empty();
        $sequence->getIterator();

        $this->expectException(SequenceConsumedException::class);
        $operation($sequence);
    }

    /** @param callable(Sequence<mixed>, \RuntimeException): Sequence<mixed> $operation */
    #[DataProvider('throwingOperators')]
    public function testOperatorCallbackExceptionsKeepTheirIdentityAndPreventReuse(callable $operation): void
    {
        $expected = new \RuntimeException('callback failed');
        $sequence = Sequence::of('value');

        try {
            $operation($sequence, $expected)->toArray();
            self::fail('The callback exception was not thrown.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        $this->expectException(SequenceConsumedException::class);
        $sequence->toArray();
    }

    public function testCallbackIsReleasedAfterConsumptionCompletes(): void
    {
        $mapper = static fn(int $value): int => $value;
        $mapperReference = \WeakReference::create($mapper);
        $sequence = Sequence::from([1])->map($mapper);
        unset($mapper);

        self::assertSame([1], $sequence->toArray());

        self::assertNull($mapperReference->get());
    }

    public function testCallbackIsReleasedAfterAStoredOutputIteratorCompletes(): void
    {
        $mapper = static fn(int $value): int => $value;
        $mapperReference = \WeakReference::create($mapper);
        $sequence = Sequence::from([1])->map($mapper);
        unset($mapper);

        $iterator = $sequence->getIterator();
        self::assertSame([1], iterator_to_array($iterator));

        self::assertNull($mapperReference->get());
    }

    /** @return iterable<string, array{callable(Sequence<mixed>): mixed}> */
    public static function instanceOperations(): iterable
    {
        yield 'getIterator' => [static fn(Sequence $sequence): mixed => $sequence->getIterator()];
        yield 'map' => [
            static fn(Sequence $sequence): mixed => $sequence->map(static fn(mixed $value): mixed => $value),
        ];
        yield 'filter' => [
            static fn(Sequence $sequence): mixed => $sequence->filter(static fn(mixed $value): bool => true),
        ];
        yield 'until' => [
            static fn(Sequence $sequence): mixed => $sequence->until(static fn(mixed $value): bool => true),
        ];
        yield 'skipUntil' => [
            static fn(Sequence $sequence): mixed => $sequence->skipUntil(static fn(mixed $value): bool => true),
        ];
        yield 'flatMap' => [
            static fn(Sequence $sequence): mixed => $sequence->flatMap(static fn(mixed $value): iterable => [$value]),
        ];
        yield 'take' => [static fn(Sequence $sequence): mixed => $sequence->take(1)];
        yield 'drop' => [static fn(Sequence $sequence): mixed => $sequence->drop(1)];
        yield 'toArray' => [static fn(Sequence $sequence): mixed => $sequence->toArray()];
        yield 'toCollection' => [static fn(Sequence $sequence): mixed => $sequence->toCollection()];
        yield 'fold' => [
            static fn(Sequence $sequence): mixed => $sequence->fold(
                null,
                static fn(mixed $state, mixed $value): mixed => $state,
            ),
        ];
    }

    /** @return iterable<string, array{callable(Sequence<mixed>, \RuntimeException): Sequence<mixed>}> */
    public static function throwingOperators(): iterable
    {
        yield 'filter' => [
            static fn(
                Sequence $sequence,
                \RuntimeException $exception,
            ): Sequence => $sequence->filter(static function () use ($exception): never {
                throw $exception;
            }),
        ];
        yield 'flatMap' => [
            static fn(Sequence $sequence, \RuntimeException $exception): Sequence => $sequence->flatMap(
                static fn(mixed $value): iterable => self::throwAsIterable($exception),
            ),
        ];
        yield 'until' => [
            static fn(
                Sequence $sequence,
                \RuntimeException $exception,
            ): Sequence => $sequence->until(static function () use ($exception): never {
                throw $exception;
            }),
        ];
        yield 'skipUntil' => [
            static fn(
                Sequence $sequence,
                \RuntimeException $exception,
            ): Sequence => $sequence->skipUntil(static function () use ($exception): never {
                throw $exception;
            }),
        ];
    }

    public function testFoldRequiresStateAndCallbackAndSequenceIsNotCountable(): void
    {
        self::assertSame(2, new ReflectionMethod(Sequence::class, 'fold')->getNumberOfRequiredParameters());
        self::assertNotContains(Countable::class, class_implements(Sequence::class));
    }

    /** @return iterable<mixed> */
    private static function throwAsIterable(\RuntimeException $exception): iterable
    {
        throw $exception;
    }
}

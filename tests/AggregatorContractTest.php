<?php

declare(strict_types=1);

namespace Itera\Tests;

use Itera\Aggregator;
use Itera\Sequence;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AggregatorContractTest extends TestCase
{
    public function testAggregatorHasNoPublicCallbackConstructorOrExecutionLifecycleProtocol(): void
    {
        $reflection = new ReflectionClass(Aggregator::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue($reflection->isFinal());
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertNotContains('fromCallbacks', get_class_methods(Aggregator::class));
        self::assertNotContains('initial', get_class_methods(Aggregator::class));
        self::assertNotContains('step', get_class_methods(Aggregator::class));
        self::assertNotContains('complete', get_class_methods(Aggregator::class));
        self::assertNotContains('finish', get_class_methods(Aggregator::class));
    }

    public function testSequenceAggregateAndBuiltInFactoriesAreAvailable(): void
    {
        self::assertSame(1, new ReflectionMethod(Sequence::class, 'aggregate')->getNumberOfRequiredParameters());
        self::assertTrue(function_exists('Itera\\Aggregator\\count'));
        self::assertTrue(function_exists('Itera\\Aggregator\\any'));
        self::assertTrue(function_exists('Itera\\Aggregator\\all'));
        self::assertTrue(function_exists('Itera\\Aggregator\\collect'));
        self::assertTrue(function_exists('Itera\\Aggregator\\associate'));
        self::assertFalse(function_exists('Itera\\Aggregator\\fold'));
    }
}

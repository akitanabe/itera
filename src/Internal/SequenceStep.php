<?php

declare(strict_types=1);

namespace Itera\Internal;

/** @internal */
final class SequenceStep
{
    public const string FORWARD = 'forward';
    public const string SKIP = 'skip';
    public const string EXPAND = 'expand';
    public const string LAST = 'last';

    private function __construct(
        public readonly string $kind,
        public readonly mixed $value = null,
    ) {}

    public static function forward(mixed $value): self
    {
        return new self(self::FORWARD, $value);
    }

    public static function skip(): self
    {
        return new self(self::SKIP);
    }

    /** @param iterable<mixed> $values */
    public static function expand(iterable $values): self
    {
        return new self(self::EXPAND, $values);
    }

    public static function last(mixed $value): self
    {
        return new self(self::LAST, $value);
    }
}

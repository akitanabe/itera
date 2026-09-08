<?php

declare(strict_types=1);

namespace Itera\Internal;

use ArrayIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use Traversable;

/** @internal */
final class SequenceInputFrame
{
    private bool $started = false;

    private function __construct(
        private readonly Iterator $input,
        public readonly int $startOperation,
    ) {}

    /** @param iterable<mixed> $input */
    public static function from(iterable $input, int $startOperation): self
    {
        $input = self::resolve($input);
        $input = match (true) {
            is_array($input) => new ArrayIterator($input),
            $input instanceof Iterator => $input,
            default => self::traverse($input),
        };

        return new self($input, $startOperation);
    }

    /**
     * @param iterable<mixed> $input
     * @return iterable<mixed>
     */
    public static function resolve(iterable $input): iterable
    {
        while ($input instanceof IteratorAggregate) {
            $input = $input->getIterator();
        }

        return $input;
    }

    /**
     * @param Traversable<mixed, mixed> $input
     * @return Generator<int, mixed, void, void>
     */
    private static function traverse(Traversable $input): Generator
    {
        foreach ($input as $value) {
            yield $value;
        }
    }

    /** @return array{found: false}|array{found: true, value: mixed} */
    public function read(): array
    {
        if ($this->started) {
            $this->input->next();
        }

        if (!$this->input->valid()) {
            return ['found' => false];
        }

        $value = $this->input->current();
        $this->started = true;

        return ['found' => true, 'value' => $value];
    }
}

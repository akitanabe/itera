<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

final class FoldUnstableAccumulator
{
    public function analyse(): int|string
    {
        return Sequence::from([1])->fold(
            0,
            // @phpstan-ignore argument.type (This fixture proves that a fold step cannot change its accepted state type.)
            static fn(int $state, int $value): string => (string) ($state + $value),
        );
    }
}

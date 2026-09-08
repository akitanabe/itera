<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Pipe\aggregate;

final class PipeInvalidProperty
{
    public function analyse(): bool
    {
        return Sequence::from([new InvalidPropertyUser()])
            |> aggregate(
                // @phpstan-ignore property.notFound (This fixture proves that pipe context reaches an inline callback.)
                any(static fn($user): bool => $user->missing === true),
            );
    }
}

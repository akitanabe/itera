<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\any;
use function Itera\Aggregator\combine;
use function Itera\Pipe\aggregate;

final class PipeCombineInvalidProperty
{
    /** @return array{invalid: bool} */
    public function analyse(): array
    {
        return Sequence::from([new InvalidPropertyUser()])
            |> aggregate(combine(
                // @phpstan-ignore property.notFound (Pipe context reaches a callback nested directly in combine.)
                invalid: any(static fn($user): bool => $user->missing === true),
            ));
    }
}

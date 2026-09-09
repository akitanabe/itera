<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Sequence;

use function Itera\Aggregator\countBy;
use function Itera\Aggregator\groupBy;
use function Itera\Aggregator\partition;

final class AggregatorGroupingInvalidContracts
{
    public function incompatibleCallbackInput(): mixed
    {
        // @phpstan-ignore argument.type (This fixture proves that partition rejects a callback for an incompatible input type.)
        return Sequence::from([1])->aggregate(partition(static fn(string $value): bool => $value !== ''));
    }

    /** @mago-expect lint:prefer-arrow-function */
    public function invalidGroupKey(): mixed
    {
        // @phpstan-ignore argument.type, argument.templateType (This fixture proves that groupBy rejects a non-array-key callback result.)
        return Sequence::from([new InvalidPropertyUser()])->aggregate(groupBy(static function ($user) {
            return $user->active;
        }));
    }

    public function invalidCountKey(): mixed
    {
        // @phpstan-ignore argument.type, argument.templateType (This fixture proves that countBy rejects a non-array-key callback result.)
        return Sequence::from([new InvalidPropertyUser()])->aggregate(countBy(static fn($user) => $user->active));
    }
}

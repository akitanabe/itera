<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use Itera\Collection;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;

final class UniqueAggregatorType extends GenericObjectType
{
    public function __construct()
    {
        parent::__construct(Aggregator::class, [
            new MixedType(),
            new GenericObjectType(Collection::class, [new MixedType()]),
        ]);
    }
}

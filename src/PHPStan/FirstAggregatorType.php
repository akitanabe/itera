<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;

final class FirstAggregatorType extends GenericObjectType
{
    public function __construct()
    {
        parent::__construct(Aggregator::class, [new MixedType(), new MixedType()]);
    }
}

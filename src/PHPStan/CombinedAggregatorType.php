<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;

final class CombinedAggregatorType extends GenericObjectType
{
    public function __construct(
        private readonly Type $childrenType,
    ) {
        parent::__construct(Aggregator::class, [
            AggregatorTypeResolver::inputType($childrenType),
            AggregatorTypeResolver::resultType($childrenType),
        ]);
    }

    public function getChildrenType(): Type
    {
        return $this->childrenType;
    }
}

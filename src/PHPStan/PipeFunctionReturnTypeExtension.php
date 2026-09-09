<?php

declare(strict_types=1);

namespace Itera\PHPStan;

use Itera\Aggregator;
use Itera\Collection;
use Itera\Map;
use Itera\Sequence;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\ExprHandler\Helper\ClosureTypeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeFactory;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeScope;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

final class PipeFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    private const array FUNCTIONS = [
        'Itera\\Pipe\\aggregate' => true,
        'Itera\\Pipe\\associate' => true,
    ];

    public function __construct(
        private readonly ClosureTypeResolver $closureTypes,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return array_key_exists($functionReflection->getName(), self::FUNCTIONS);
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $inputType = AggregatorCallContext::receiverInputType($functionCall, $scope);
        if ($functionReflection->getName() === 'Itera\\Pipe\\associate') {
            $contextualInputType = AggregatorCallContext::inputType($functionCall, $scope);
            if ($contextualInputType === null) {
                return null;
            }

            return self::closure($contextualInputType, new GenericObjectType(Map::class, [
                AggregatorKeyType::fromCall($functionCall, $contextualInputType, $scope, $this->closureTypes),
                $contextualInputType,
            ]));
        }

        $definition = $functionCall->getArgs()[0]->value ?? null;
        if ($definition === null) {
            return null;
        }

        $definitionType = $scope->getType($definition);
        if ($definitionType instanceof CombinedAggregatorType) {
            $definitionInputType = AggregatorTypeResolver::inputType($definitionType->getChildrenType());
            if ($inputType === null) {
                return self::polymorphicCombinedClosure($definitionType, $definitionInputType);
            }
            if (!$definitionInputType->isSuperTypeOf($inputType)->yes()) {
                return null;
            }

            return self::closure($inputType, AggregatorTypeResolver::resultType(
                $definitionType->getChildrenType(),
                $inputType,
            ));
        }
        if ($definitionType instanceof CollectAggregatorType || $definitionType instanceof UniqueAggregatorType) {
            return (
                $inputType === null
                    ? self::polymorphicCollectionClosure()
                    : self::closure($inputType, new GenericObjectType(Collection::class, [$inputType]))
            );
        }
        if ($definitionType instanceof FirstAggregatorType) {
            return $inputType === null
                ? self::polymorphicFirstClosure()
                : self::closure($inputType, TypeCombinator::addNull($inputType));
        }

        if ($inputType !== null) {
            $definitionInputType = $definitionType->getTemplateType(Aggregator::class, 'T');
            if (!$definitionInputType->isSuperTypeOf($inputType)->yes()) {
                return null;
            }

            return self::closure($inputType, $definitionType->getTemplateType(Aggregator::class, 'R'));
        }

        return null;
    }

    private static function closure(Type $inputType, Type $returnType): ClosureType
    {
        return new ClosureType(
            [
                new AggregatorClosureParameter(new GenericObjectType(Sequence::class, [$inputType])),
            ],
            $returnType,
            false,
        );
    }

    private static function polymorphicCollectionClosure(): ClosureType
    {
        // @phpstan-ignore phpstanApi.method (Saved polymorphic pipe closures require a template type; this is verified against the documented supported PHPStan version.)
        $template = TemplateTypeFactory::create(
            // @phpstan-ignore phpstanApi.method (The public extension API has no template-scope factory; compatibility is verified against the documented supported PHPStan version.)
            TemplateTypeScope::createWithFunction('Itera\\Pipe\\aggregate'),
            'T',
            new MixedType(),
            TemplateTypeVariance::createInvariant(),
        );

        return new ClosureType(
            [new AggregatorClosureParameter(new GenericObjectType(Sequence::class, [$template]))],
            new GenericObjectType(Collection::class, [$template]),
            false,
            new TemplateTypeMap(['T' => $template]),
        );
    }

    private static function polymorphicFirstClosure(): ClosureType
    {
        // @phpstan-ignore phpstanApi.method (Saved polymorphic pipe closures require a template type; this is verified against the documented supported PHPStan version.)
        $template = TemplateTypeFactory::create(
            // @phpstan-ignore phpstanApi.method (The public extension API has no template-scope factory; compatibility is verified against the documented supported PHPStan version.)
            TemplateTypeScope::createWithFunction('Itera\\Pipe\\aggregate'),
            'T',
            new MixedType(),
            TemplateTypeVariance::createInvariant(),
        );

        return new ClosureType(
            [new AggregatorClosureParameter(new GenericObjectType(Sequence::class, [$template]))],
            new UnionType([$template, new NullType()]),
            false,
            new TemplateTypeMap(['T' => $template]),
        );
    }

    private static function polymorphicCombinedClosure(
        CombinedAggregatorType $aggregatorType,
        Type $inputBound,
    ): ClosureType {
        // @phpstan-ignore phpstanApi.method (Saved polymorphic pipe closures require a template type; this is verified against the documented supported PHPStan version.)
        $template = TemplateTypeFactory::create(
            // @phpstan-ignore phpstanApi.method (The public extension API has no template-scope factory; compatibility is verified against the documented supported PHPStan version.)
            TemplateTypeScope::createWithFunction('Itera\\Pipe\\aggregate'),
            'T',
            $inputBound,
            TemplateTypeVariance::createInvariant(),
        );

        return new ClosureType(
            [new AggregatorClosureParameter(new GenericObjectType(Sequence::class, [$template]))],
            AggregatorTypeResolver::resultType($aggregatorType->getChildrenType(), $template),
            false,
            new TemplateTypeMap(['T' => $template]),
        );
    }
}

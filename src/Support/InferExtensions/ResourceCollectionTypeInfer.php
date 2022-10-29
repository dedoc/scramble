<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;

class ResourceCollectionTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (! $scope->isInClass() || ! $scope->class()->isInstanceOf(ResourceCollection::class)) {
            return null;
        }

        /** parent::toArray() in `toArray` */
        if (
            ($scope->isInFunction() && $scope->function()->name === 'toArray')
            && $node instanceof Node\Expr\StaticCall
            && ($node->class instanceof Node\Name && $node->class->toString() === 'parent')
            && ($node->name->name ?? null) === 'toArray'
        ) {
            return $this->getBasicCollectionType($scope->class());
        }

        /** $this->collection */
        if (
            $node instanceof Node\Expr\PropertyFetch
            && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'collection'
        ) {
            return $this->getBasicCollectionType($scope->class());
        }

        return null;
    }

    public function getBasicCollectionType(ObjectType $classType)
    {
        $collectingClassType = $this->getCollectingClassType($classType);

        if (! $collectingClassType) {
            return new UnknownType('Cannot find a type of the collecting class.');
        }

        return new ArrayType([
            new ArrayItemType_(0, new ObjectType($collectingClassType->value)),
        ]);
    }

    private function getCollectingClassType(ObjectType $classType): ?LiteralStringType
    {
        $collectingClassType = $classType->getPropertyFetchType('collects');

        if (! $collectingClassType instanceof LiteralStringType) {
            if (
                str_ends_with($classType->name, 'Collection') &&
                (class_exists($class = Str::replaceLast('Collection', '', $classType->name)) ||
                    class_exists($class = Str::replaceLast('Collection', 'Resource', $classType->name)))
            ) {
                $collectingClassType = new LiteralStringType($class);
            } else {
                return null;
            }
        }

        return $collectingClassType;
    }
}

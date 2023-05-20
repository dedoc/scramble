<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
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
        if (! $scope->classDefinition()?->isInstanceOf(ResourceCollection::class)) {
            return null;
        }

        /** parent::toArray() in `toArray` */
        if (
            ($scope->isInFunction() && $scope->functionDefinition()->type->name === 'toArray')
            && $node instanceof Node\Expr\StaticCall
            && ($node->class instanceof Node\Name && $node->class->toString() === 'parent')
            && ($node->name->name ?? null) === 'toArray'
        ) {
            return $this->getBasicCollectionType($scope->classDefinition());
        }

        /** $this->collection */
        if (
            $node instanceof Node\Expr\PropertyFetch
            && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'collection'
        ) {
            return $this->getBasicCollectionType($scope->classDefinition());
        }

        return null;
    }

    public function getBasicCollectionType(ClassDefinition $classDefinition)
    {
        $collectingClassType = $this->getCollectingClassType($classDefinition);

        if (! $collectingClassType) {
            return new UnknownType('Cannot find a type of the collecting class.');
        }

        return new ArrayType([
            new ArrayItemType_(0, new ObjectType($collectingClassType->value)),
        ]);
    }

    private function getCollectingClassType(ClassDefinition $classDefinition): ?LiteralStringType
    {
        $collectingClassDefinition = $classDefinition->getPropertyDefinition('collects');

        $collectingClassType = $collectingClassDefinition?->defaultType;

        if (! $collectingClassType instanceof LiteralStringType) {
            if (
                str_ends_with($classDefinition->name, 'Collection') &&
                (class_exists($class = Str::replaceLast('Collection', '', $classDefinition->name)) ||
                    class_exists($class = Str::replaceLast('Collection', 'Resource', $classDefinition->name)))
            ) {
                $collectingClassType = new LiteralStringType($class);
            } else {
                return null;
            }
        }

        return $collectingClassType;
    }
}

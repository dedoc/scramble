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
use PhpParser\Node;
use PhpParser\Node\Expr;

class ResourceCollectionTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (! $scope->isInClass() || ! optional($scope->class())->isInstanceOf(ResourceCollection::class)) {
            return null;
        }

        /** $this->collection */
        if (
            $node instanceof Node\Expr\PropertyFetch
            && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'collection'
        ) {
            $collectingClassType = $scope->class()->getPropertyFetchType('collects');

            if (! $collectingClassType instanceof LiteralStringType) {
                return new UnknownType('Cannot infer collection collecting class.');
            }

            return new ArrayType([
                new ArrayItemType_(0, new ObjectType($collectingClassType->value)),
            ]);
        }

        return null;
    }
}

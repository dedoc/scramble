<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;

class JsonResourceStaticCallsTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (! ($node instanceof Node\Expr\StaticCall)) {
            return null;
        }

        if (! ($node->class instanceof Node\Name && is_a($node->class->toString(), JsonResource::class, true))) {
            return null;
        }

        if (! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->toString() === 'collection') {
            return new Generic(
                new ObjectType(AnonymousResourceCollection::class),
                [new ObjectType($node->class->toString())],
            );
        }

        if ($node->name->toString() === 'make') {
            return new ObjectType($node->class->toString());
        }

        return null;
    }
}

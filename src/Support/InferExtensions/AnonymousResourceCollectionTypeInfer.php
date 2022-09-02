<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;

class AnonymousResourceCollectionTypeInfer
{
    public function getNodeReturnType(Node $node)
    {
        if (! ($node instanceof Node\Expr\StaticCall)) {
            return null;
        }

        if (! ($node->class instanceof Node\Name && is_a($node->class->toString(), JsonResource::class, true))) {
            return null;
        }

        if (! ($node->name instanceof Node\Identifier && $node->name->toString() === 'collection')) {
            return null;
        }

        return new Generic(
            new ObjectType(AnonymousResourceCollection::class),
            [new ObjectType($node->class->toString())],
        );
    }
}

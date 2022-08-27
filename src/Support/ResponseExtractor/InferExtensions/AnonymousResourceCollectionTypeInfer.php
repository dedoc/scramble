<?php

namespace Dedoc\Scramble\Support\ResponseExtractor\InferExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Identifier;
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
            new Identifier(AnonymousResourceCollection::class),
            [new Identifier($node->class->toString())],
        );
    }
}

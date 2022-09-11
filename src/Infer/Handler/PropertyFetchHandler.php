<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class PropertyFetchHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\PropertyFetch;
    }

    public function leave(Node\Expr\PropertyFetch $node, Scope $scope)
    {
        // Only string property names support.
        if (! $name = ($node->name->name ?? null)) {
            return null;
        }

        $type = $scope->getType($node->var);

        $scope->setType($node, $type->getPropertyFetchType($name));
    }
}

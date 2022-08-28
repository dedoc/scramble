<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use PhpParser\Node;

class PropertyFetchHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\PropertyFetch;
    }

    public function leave(Node\Expr\PropertyFetch $node)
    {
        if (! $type = $node->var->getAttribute('type')) {
            return null;
        }

        if (! $name = ($node->name->name ?? null)) {
            return null;
        }

        $node->setAttribute('type', $type->getPropertyFetchType($name));
    }
}

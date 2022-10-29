<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class PropertyHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Property;
    }

    public function leave(Node\Stmt\Property $node, Scope $scope)
    {
        $classType = $scope->class();

        foreach ($node->props as $prop) {
            $classType->properties = array_merge($classType->properties, [
                $prop->name->name => $prop->default
                    ? $scope->getType($prop->default)
                    : ($node->type
                        ? TypeHelper::createTypeFromTypeNode($node->type)
                        : new UnknownType('No property default type')
                    ),
            ]);
        }
    }
}

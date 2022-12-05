<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

class PropertyHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Stmt\Property;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }
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

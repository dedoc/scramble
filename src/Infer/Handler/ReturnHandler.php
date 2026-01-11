<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;

class ReturnHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Return_;
    }

    public function leave(Node\Stmt\Return_ $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        $fnDefinition = $scope->functionDefinition();

        $fnDefinition->type->setReturnType(
            TypeHelper::mergeTypes(
                $node->expr ? $scope->getType($node->expr) : new VoidType,
                $fnDefinition->type->getReturnType(),
            )
        );
    }
}

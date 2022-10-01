<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionType;
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
        $fnType = $scope->context->function;

        if (! $fnType instanceof FunctionType) {
            return;
        }

        $fnType->setReturnType(
            TypeHelper::mergeTypes(
                $node->expr ? $scope->getType($node->expr) : new VoidType,
                $fnType->getReturnType(),
            )
        );
    }
}

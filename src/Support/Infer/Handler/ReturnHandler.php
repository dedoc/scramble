<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class ReturnHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Return_;
    }

    public function leave(Node\Stmt\Return_ $node, Scope $scope)
    {
        $fnType = $scope->context->function;

        if (! ($fnType instanceof FunctionType)) {
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
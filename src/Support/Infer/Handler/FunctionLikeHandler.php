<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;

class FunctionLikeHandler implements CreatesScope
{
    public function createScope(Scope $scope): Scope
    {
        return $scope->createChildScope(clone $scope->context);
    }

    public function shouldHandle($node)
    {
        return $node instanceof FunctionLike;
    }

    public function enter(FunctionLike $node, Scope $scope)
    {
        // when entering function node, the only thing we need/want to do
        // is to set node param types to scope.
        // Also, if here we add a reference to the function node type, it may allow us to
        // set function return types not in leave function, but in the return handlers.
        $scope->setType($node, $fnType = new FunctionType);

        $scope->context->setFunction($fnType);
    }

    public function leave(FunctionLike $node, Scope $scope)
    {
        $type = $scope->context->function;

        if ($returnTypeAnnotation = $node->getReturnType()) {
            $type->setReturnType(TypeHelper::createTypeFromTypeNode($returnTypeAnnotation) ?: new VoidType);
        } else {
            // Simple way of handling the arrow functions, as they do not have a return statement.
            // So here we just create a "virtual" return and processing it as by default.
            if ($node instanceof Node\Expr\ArrowFunction) {
                (new ReturnHandler)->leave(
                    new Node\Stmt\Return_($node->expr, $node->getAttributes()),
                    $scope,
                );
            }
        }
    }
}

<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Contracts\EnterTrait;
use Dedoc\Scramble\Infer\Contracts\HandlerInterface;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;

class ReturnHandler implements HandlerInterface
{
    use EnterTrait;

    public function shouldHandle(Node $node): bool
    {
        return $node instanceof Node\Stmt\Return_;
    }

    public function leave(Node $node, Scope $scope): void
    {
        if (!$this->shouldHandle($node)) {
            return;
        }

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

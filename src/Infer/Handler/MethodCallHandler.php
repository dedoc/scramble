<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\UnresolvableArgumentTypeBag;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;

class MethodCallHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Expr\MethodCall;
    }

    public function leave(Node\Expr\MethodCall $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        // Only string method names support.
        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        $calleeType = $scope->getType($node->var);

        if (! $calleeType instanceof ObjectType) {
            return;
        }

        $event = new MethodCallEvent(
            $calleeType,
            $node->name->name,
            $scope,
            new UnresolvableArgumentTypeBag($scope->getArgsTypes($node->args)),
            $calleeType->name
        );

        $exceptions = app(ExtensionsBroker::class)->getMethodCallExceptions($event);

        if (empty($exceptions)) {
            return;
        }

        $fnDefinition = $scope->functionDefinition();
        $fnDefinition->type->exceptions = array_merge(
            $fnDefinition->type->exceptions,
            $exceptions,
        );
    }
}

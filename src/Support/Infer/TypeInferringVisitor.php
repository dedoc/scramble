<?php

namespace Dedoc\Scramble\Support\Infer;

use Dedoc\Scramble\Support\Infer\Handler\ArrayHandler;
use Dedoc\Scramble\Support\Infer\Handler\ArrayItemHandler;
use Dedoc\Scramble\Support\Infer\Handler\ClassHandler;
use Dedoc\Scramble\Support\Infer\Handler\CreatesScope;
use Dedoc\Scramble\Support\Infer\Handler\FunctionLikeHandler;
use Dedoc\Scramble\Support\Infer\Handler\NewHandler;
use Dedoc\Scramble\Support\Infer\Handler\PropertyFetchHandler;
use Dedoc\Scramble\Support\Infer\Handler\ReturnTypeGettingExtensions;
use Dedoc\Scramble\Support\Infer\Handler\ScalarHandler;
use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Infer\Scope\ScopeContext;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypeInferringVisitor extends NodeVisitorAbstract
{
    private Scope $scope;

    private $namesResolver;

    public function __construct(callable $namesResolver)
    {
        $this->namesResolver = $namesResolver;
    }

    public function enterNode(Node $node)
    {
        $scope = $this->getOrCreateScope();
        $node->setAttribute('scope', $scope);

        foreach ($this->getHandlers() as $handlerClass) {
            $handlerInstance = new $handlerClass;

            if (! $handlerInstance->shouldHandle($node)) {
                continue;
            }

            if ($handlerInstance instanceof CreatesScope) {
                $this->scope = $handlerInstance->createScope($scope);
                $node->setAttribute('scope', $this->scope);
            }

            if (method_exists($handlerInstance, 'enter')) {
                $handlerInstance->enter($node);
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        foreach ($this->getHandlers() as $handlerClass) {
            $handlerInstance = new $handlerClass;

            if (! $handlerInstance->shouldHandle($node)) {
                continue;
            }

            if ($handlerInstance instanceof CreatesScope) {
                $this->scope = $node->getAttribute('scope')->parentScope;
            }

            if (method_exists($handlerInstance, 'leave')) {
                $handlerInstance->leave($node);
            }
        }

        return null;
    }

    private function getHandlers()
    {
        return [
            FunctionLikeHandler::class,
            ScalarHandler::class,
            NewHandler::class,
            ClassHandler::class,
            PropertyFetchHandler::class,
            ArrayHandler::class,
            ArrayItemHandler::class,
            ReturnTypeGettingExtensions::class,
        ];
    }

    private function getOrCreateScope()
    {
        if (! isset($this->scope)) {
            $this->scope = new Scope(new ScopeContext, $this->namesResolver);
        }
        return $this->scope;
    }
}

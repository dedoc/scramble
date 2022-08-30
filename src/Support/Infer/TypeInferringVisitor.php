<?php

namespace Dedoc\Scramble\Support\Infer;

use Dedoc\Scramble\Support\Infer\Handler\ArrayHandler;
use Dedoc\Scramble\Support\Infer\Handler\ArrayItemHandler;
use Dedoc\Scramble\Support\Infer\Handler\ClassHandler;
use Dedoc\Scramble\Support\Infer\Handler\CreatesScope;
use Dedoc\Scramble\Support\Infer\Handler\FunctionLikeHandler;
use Dedoc\Scramble\Support\Infer\Handler\MethodCallHandler;
use Dedoc\Scramble\Support\Infer\Handler\NewHandler;
use Dedoc\Scramble\Support\Infer\Handler\PropertyFetchHandler;
use Dedoc\Scramble\Support\Infer\Handler\ReturnHandler;
use Dedoc\Scramble\Support\Infer\Handler\ReturnTypeGettingExtensions;
use Dedoc\Scramble\Support\Infer\Handler\ScalarHandler;
use Dedoc\Scramble\Support\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Infer\Scope\ScopeContext;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypeInferringVisitor extends NodeVisitorAbstract
{
    public Scope $scope;

    private $namesResolver;

    public function __construct(callable $namesResolver)
    {
        $this->namesResolver = $namesResolver;
    }

    public function enterNode(Node $node)
    {
        $scope = $this->getOrCreateScope();

        foreach ($this->getHandlers() as $handlerClass) {
            $handlerInstance = new $handlerClass;

            if (! $handlerInstance->shouldHandle($node)) {
                continue;
            }

            if ($handlerInstance instanceof CreatesScope) {
                $this->scope = $handlerInstance->createScope($scope);
            }

            if (method_exists($handlerInstance, 'enter')) {
                $handlerInstance->enter($node, $this->scope);
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

            if (method_exists($handlerInstance, 'leave')) {
                $handlerInstance->leave($node, $this->scope);
            }

            if ($handlerInstance instanceof CreatesScope) {
                $this->scope = $this->scope->parentScope;
            }
        }

        return null;
    }

    private function getHandlers()
    {
        return [
            FunctionLikeHandler::class,
            NewHandler::class,
            ClassHandler::class,
            PropertyFetchHandler::class,
            ArrayHandler::class,
            ArrayItemHandler::class,
            ReturnHandler::class,
//            MethodCallHandler::class,
            ReturnTypeGettingExtensions::class,
        ];
    }

    private function getOrCreateScope()
    {
        if (! isset($this->scope)) {
            $this->scope = new Scope(
                new NodeTypesResolver,
                new ScopeContext,
                $this->namesResolver
            );
        }

        return $this->scope;
    }
}

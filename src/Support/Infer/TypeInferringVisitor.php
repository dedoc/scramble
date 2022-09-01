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
use Dedoc\Scramble\Support\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Support\Infer\Scope\PendingTypes;
use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\TypeWalker;
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

        if ($node instanceof Node\FunctionLike) {
            /** @var FunctionType $type */
            $type = $this->scope->getType($node);

            // When there is a referenced type in fn return, we want to add it to the pending
            // resolution types, so it can be resolved later.
            if (count(TypeWalker::find($type->getReturnType(), fn ($t) => $t instanceof PendingReturnType))) {
                $this->scope->pending->addReference(
                    $type,
                    function ($pendingType, $resolvedPendingType) use ($type) {
                        $type->setReturnType(
                            TypeWalker::replace($type->getReturnType(), $pendingType, $resolvedPendingType)
                        );
                    }
                );
            }

            // And in the end, after the function is analyzed, we try to resolve all pending types
            // that exist in the current global check run.
            $this->scope->pending->resolve();
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
            ReturnTypeGettingExtensions::class,
        ];
    }

    private function getOrCreateScope()
    {
        if (! isset($this->scope)) {
            $this->scope = new Scope(
                new NodeTypesResolver,
                new PendingTypes,
                new ScopeContext,
                $this->namesResolver
            );
        }

        return $this->scope;
    }
}

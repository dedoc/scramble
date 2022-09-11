<?php

namespace Dedoc\Scramble\Support\Infer;

use Dedoc\Scramble\Support\Infer\Handler\ArrayHandler;
use Dedoc\Scramble\Support\Infer\Handler\ArrayItemHandler;
use Dedoc\Scramble\Support\Infer\Handler\ClassHandler;
use Dedoc\Scramble\Support\Infer\Handler\CreatesScope;
use Dedoc\Scramble\Support\Infer\Handler\FunctionLikeHandler;
use Dedoc\Scramble\Support\Infer\Handler\NewHandler;
use Dedoc\Scramble\Support\Infer\Handler\PropertyFetchHandler;
use Dedoc\Scramble\Support\Infer\Handler\ReturnHandler;
use Dedoc\Scramble\Support\Infer\Handler\ExpressionTypeInferringExtensions;
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

    private array $extensions;

    private array $handlers = [];

    public function __construct(callable $namesResolver, array $extensions = [])
    {
        $this->namesResolver = $namesResolver;
        $this->extensions = $extensions;

        $this->handlers = [
            new FunctionLikeHandler(),
            new NewHandler(),
            new ClassHandler(),
            new PropertyFetchHandler(),
            new ArrayHandler(),
            new ArrayItemHandler(),
            new ReturnHandler(),
            new ExpressionTypeInferringExtensions($this->extensions),
        ];
    }

    public function enterNode(Node $node)
    {
        $scope = $this->getOrCreateScope();

        foreach ($this->handlers as $handler) {
            if (! $handler->shouldHandle($node)) {
                continue;
            }

            if ($handler instanceof CreatesScope) {
                $this->scope = $handler->createScope($scope);
            }

            if (method_exists($handler, 'enter')) {
                $handler->enter($node, $this->scope);
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        foreach ($this->handlers as $handler) {
            if (! $handler->shouldHandle($node)) {
                continue;
            }

            if (method_exists($handler, 'leave')) {
                $handler->leave($node, $this->scope);
            }

            if ($handler instanceof CreatesScope) {
                $this->scope = $this->scope->parentScope;
            }
        }

        if ($node instanceof Node\FunctionLike) {
            /** @var FunctionType $type */
            $type = $this->scope->getType($node);

            // When there is a referenced type in fn return, we want to add it to the pending
            // resolution types, so it can be resolved later.
            if ($pendingTypesCount = count((new TypeWalker)->find($type->getReturnType(), fn ($t) => $t instanceof PendingReturnType))) {
                $this->scope->pending->addReference(
                    $type,
                    function ($pendingType, $resolvedPendingType) use ($type) {
                        $type->setReturnType(
                            TypeWalker::replace($type->getReturnType(), $pendingType, $resolvedPendingType)
                        );
                    },
                    $pendingTypesCount,
                );
            }

            // And in the end, after the function is analyzed, we try to resolve all pending types
            // that exist in the current global check run.
            $this->scope->pending->resolve();
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        $this->scope->pending->resolveAllPendingIntoUnknowns();
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

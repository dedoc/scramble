<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Handler\ArrayHandler;
use Dedoc\Scramble\Infer\Handler\ArrayItemHandler;
use Dedoc\Scramble\Infer\Handler\AssignHandler;
use Dedoc\Scramble\Infer\Handler\ClassHandler;
use Dedoc\Scramble\Infer\Handler\CreatesScope;
use Dedoc\Scramble\Infer\Handler\ExceptionInferringExtensions;
use Dedoc\Scramble\Infer\Handler\ExpressionTypeInferringExtensions;
use Dedoc\Scramble\Infer\Handler\FunctionLikeHandler;
use Dedoc\Scramble\Infer\Handler\NewHandler;
use Dedoc\Scramble\Infer\Handler\PropertyFetchHandler;
use Dedoc\Scramble\Infer\Handler\PropertyHandler;
use Dedoc\Scramble\Infer\Handler\ReturnHandler;
use Dedoc\Scramble\Infer\Handler\ThrowHandler;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\PendingTypes;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypeInferer extends NodeVisitorAbstract
{
    public Scope $scope;

    private array $handlers;

    private FileNameResolver $namesResolver;

    public function __construct(FileNameResolver $namesResolver, array $extensions = [], array $handlers = [])
    {
        $this->namesResolver = $namesResolver;

        $this->handlers = [
            new FunctionLikeHandler(),
            new AssignHandler(),
            new NewHandler(),
            new ClassHandler(),
            new PropertyHandler(),
            new PropertyFetchHandler(),
            new ArrayHandler(),
            new ArrayItemHandler(),
            new ReturnHandler(),
            new ThrowHandler(),
            new ExpressionTypeInferringExtensions(array_values(array_filter(
                $extensions,
                fn ($ext) => $ext instanceof ExpressionTypeInferExtension,
            ))),
            new ExceptionInferringExtensions(array_values(array_filter(
                $extensions,
                fn ($ext) => $ext instanceof ExpressionExceptionExtension,
            ))),
            ...$handlers,
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
                $this->scope = $handler->createScope($scope, $node);
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

            $pendingTypes = (new TypeWalker)->find(
                $type->getReturnType(),
                fn ($t) => $t instanceof PendingReturnType,
                fn ($t) => ! ($t instanceof ObjectType && $t->name === $this->scope->context->class->name)
            );

            // When there is a referenced type in fn return, we want to add it to the pending
            // resolution types, so it can be resolved later.
            if ($pendingTypes) {
                $this->scope->pending->addReference(
                    $type,
                    function ($pendingType, $resolvedPendingType) use ($type) {
                        $type->setReturnType(
                            TypeHelper::unpackIfArrayType((new TypeWalker)->replace($type->getReturnType(), $pendingType, $resolvedPendingType))
                        );
                    },
                    $pendingTypes,
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
                new Index,
                new NodeTypesResolver,
                new PendingTypes,
                new ScopeContext,
                $this->namesResolver,
            );
        }

        return $this->scope;
    }
}

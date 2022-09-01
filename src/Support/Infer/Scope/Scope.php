<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;

class Scope
{
    public NodeTypesResolver $nodeTypesResolver;

    public PendingTypes $pending;

    public ScopeContext $context;

    public ?Scope $parentScope;

    public $namesResolver;

    public function __construct(
        NodeTypesResolver $nodeTypesResolver,
        PendingTypes $pending,
        ScopeContext $context,
        callable $namesResolver,
        ?Scope $parentScope = null)
    {
        $this->nodeTypesResolver = $nodeTypesResolver;
        $this->pending = $pending;
        $this->context = $context;
        $this->namesResolver = $namesResolver;
        $this->parentScope = $parentScope;
        $this->pending = $pending;
    }

    public function getType(Node $node)
    {
        if ($node instanceof Node\Scalar) {
            return (new ScalarTypeGetter)($node);
        }

        if ($node instanceof Node\Expr\Variable && $node->name === 'this') {
            // @todo: remove as this should not happen - class must be always there
            return $this->context->class ?? new UnknownType;
        }

        $type = $this->nodeTypesResolver->getType($node);

        if (!$type instanceof PendingReturnType && !$type instanceof UnknownType) {
            return $type;
        }

        if ($this->nodeTypesResolver->hasType($node) && $type instanceof UnknownType) {
            return $type;
        }

        // Here we either don't have type for node cached (so we've got a freshly created
        // instance of UnknownType), or we have pending type that needs to be resolved.
        $type = $type instanceof PendingReturnType ? $type->defaultType : $type;

        if ($node instanceof Node\Expr\MethodCall) {
            // Only string method names support.
            if (!$node->name instanceof Node\Identifier) {
                return $type;
            }

            $objectType = $this->getType($node->var);

            $type = $this->setType(
                $node,
                $objectType->getMethodCallType($node->name->name, $node, $this),
            );
        }

        return $type;
    }

    public function setType(Node $node, Type $type)
    {
        if ($node instanceof CallLike) {
            // While unknown type here may be legit and type is unknown, it also may mean
            // that there is enough information to get the type. But later there may be more
            // info. So we can wait for that info to appear.
            if ($type instanceof UnknownType) {
                // Here we also may know the class and the method being called, so we can
                // save them so later deps can be resolved. If this is needed, this code
                // to be moved in the node handlers that can produce this sort of results.
                // (fn calls and property accesses, others?)
                $type = new PendingReturnType($node, $type, $this);
            }
        }
        // Also, property fetch may be pending.

        $this->nodeTypesResolver->setType($node, $type);

        return $type;
    }

    public function createChildScope(?ScopeContext $context = null, ?callable $namesResolver = null)
    {
        return new Scope(
            $this->nodeTypesResolver,
            $this->pending,
            $context ?: $this->context,
            $namesResolver ?: $this->namesResolver,
            $this,
        );
    }

    public function isInClass()
    {
        return (bool) $this->context->class;
    }

    public function class(): ?ObjectType
    {
        return $this->context->class;
    }

    public function resolveName(string $name)
    {
        return ($this->namesResolver)($name);
    }
}

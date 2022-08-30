<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;

class Scope
{
    public NodeTypesResolver $nodeTypesResolver;

    public ScopeContext $context;

    public ?Scope $parentScope;

    public $namesResolver;

    public function __construct(
        NodeTypesResolver $nodeTypesResolver,
        ScopeContext $context,
        callable $namesResolver,
        ?Scope $parentScope = null)
    {
        $this->nodeTypesResolver = $nodeTypesResolver;
        $this->context = $context;
        $this->namesResolver = $namesResolver;
        $this->parentScope = $parentScope;
    }

    public function getType(Node $node)
    {
        if ($node instanceof Node\Scalar) {
            return (new ScalarTypeGetter)($node);
        }

        return $this->nodeTypesResolver->getType($node);
    }

    public function setType(Node $node, Type $type)
    {
        $this->nodeTypesResolver->setType($node, $type);
    }

    public function createChildScope(?ScopeContext $context = null, ?callable $namesResolver = null)
    {
        return new Scope(
            $this->nodeTypesResolver,
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

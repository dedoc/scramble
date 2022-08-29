<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Type\ObjectType;

class Scope
{
    public ScopeContext $context;

    public ?Scope $parentScope;

    public $namesResolver;

    public function __construct(ScopeContext $context, callable $namesResolver, ?Scope $parentScope = null)
    {
        $this->context = $context;
        $this->namesResolver = $namesResolver;
        $this->parentScope = $parentScope;
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

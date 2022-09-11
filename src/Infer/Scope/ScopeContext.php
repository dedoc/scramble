<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;

class ScopeContext
{
    public ?ObjectType $class = null;

    public ?FunctionType $function = null;

    public function setClass(?ObjectType $class): ScopeContext
    {
        $this->class = $class;

        return $this;
    }

    public function setFunction(?FunctionType $function): ScopeContext
    {
        $this->function = $function;

        return $this;
    }
}

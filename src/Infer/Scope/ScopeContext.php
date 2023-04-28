<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;

class ScopeContext
{
    public ?ClassDefinition $classDefinition = null;

    public ?FunctionLikeDefinition $functionDefinition = null;

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

    public function setClassDefinition(ClassDefinition $classDefinition): ScopeContext
    {
        $this->classDefinition = $classDefinition;

        return $this;
    }

    public function setFunctionDefinition(FunctionLikeDefinition $functionDefinition)
    {
        $this->functionDefinition = $functionDefinition;

        return $this;
    }
}

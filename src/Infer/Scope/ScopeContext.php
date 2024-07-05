<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;

class ScopeContext
{
    public function __construct(
        public ?ClassDefinition $classDefinition = null,
        public ?FunctionLikeDefinition $functionDefinition = null,
    ) {}

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

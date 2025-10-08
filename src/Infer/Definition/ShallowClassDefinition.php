<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

class ShallowClassDefinition extends ClassDefinition
{
    protected function lazilyLoadMethodDefinition(string $name): ?FunctionLikeDefinition
    {
        return $this->methods[$name] ?? null;
    }

    public function getMethodDefinition(string $name, Scope $scope = new GlobalScope, array $indexBuilders = [], bool $withSideEffects = false): ?FunctionLikeDefinition
    {
        if ($this->isMethodDefinedInNonAstAnalyzableTrait($name)) {
            return $this->getFunctionLikeDefinitionBuiltFromReflection($name);
        }

        return $this->getMethodDefinitionWithoutAnalysis($name) ?: $this->getFunctionLikeDefinitionBuiltFromReflection($name);
    }
}

<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

class ShallowClassDefinition extends ClassDefinition
{
    public function getMethodDefinition(string $name, Scope $scope = new GlobalScope, array $indexBuilders = [], bool $withSideEffects = false)
    {
        return parent::getMethodDefinitionWithoutAnalysis($name);
    }

    public static function create(ClassDefinition $definition)
    {
        return new static(
            $definition->name,
            $definition->templateTypes,
            $definition->properties,
            $definition->methods,
            $definition->parentFqn,
        );
    }
}

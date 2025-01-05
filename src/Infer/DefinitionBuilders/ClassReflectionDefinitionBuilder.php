<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;

class ClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        public string $name,
    )
    {
    }

    public function build(): ClassDefinitionContract
    {
        $classDefinition = new ClassDefinition(name: $this->name);

        // @todo

        return $classDefinition;
    }
}

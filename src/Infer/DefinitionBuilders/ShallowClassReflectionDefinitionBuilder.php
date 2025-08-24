<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ShallowClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use ReflectionClass;

class ShallowClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    /**
     * @param  ReflectionClass<object>  $reflection
     */
    public function __construct(
        private Index $index,
        private ReflectionClass $reflection
    ) {}

    public function build(): ClassDefinition
    {
        $parentName = ($this->reflection->getParentClass() ?: null)?->name;

        $parentDefinition = $parentName ? $this->index->getClass($parentName) : null;

        /*
         * @todo consider more advanced cloning implementation.
         * Currently just cloning property definition feels alright as only its `defaultType` may change.
         */
        $classDefinition = new ShallowClassDefinition(
            name: $this->reflection->name,
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition?->properties ?: []),
            methods: $parentDefinition?->methods ?: [],
            parentFqn: $parentName,
        );

        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($classDefinition->name, $classDefinition));

        return $classDefinition;
    }
}

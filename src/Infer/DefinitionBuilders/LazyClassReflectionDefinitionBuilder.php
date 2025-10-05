<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\LazyShallowClassDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use ReflectionClass;

class LazyClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        public IndexContract $index,
        public ReflectionClass $reflection,
    ) {}

    public function build(): LazyShallowClassDefinition
    {
        $parentDefinition = ($parentName = ($this->reflection->getParentClass() ?: null)?->name)
            ? ($this->index->getClass($parentName)?->getData() ?? new ClassDefinition(name: ''))
            : new ClassDefinition(name: '');

        $classDefinitionData = new ClassDefinition(
            name: $this->reflection->name,
            templateTypes: $parentDefinition->templateTypes,
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition->properties ?: []),
            methods: $parentDefinition->methods ?: [],
            parentFqn: $parentName ?? null,
        );

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($this->reflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $this->reflection->name) {
                continue;
            }

            if ($reflectionProperty->isStatic()) {
                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                        : new UnknownType,
                );
            } else {
                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $t = new TemplateType(
                        'T'.Str::studly($reflectionProperty->name),
                        is: $reflectionProperty->hasType() ? TypeHelper::createTypeFromReflectionType($reflectionProperty->getType()) : new UnknownType,
                    ),
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                        : null,
                );
                $classDefinitionData->templateTypes[] = $t;
            }
        }

        return new LazyShallowClassDefinition($classDefinitionData);
    }
}

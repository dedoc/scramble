<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;

class ClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        public string $name,
    ) {}

    public function build(): ClassDefinition
    {
        $classReflection = new \ReflectionClass($this->name);

        $parentDefinition = ($parentName = ($classReflection->getParentClass() ?: null)?->name)
            ? (new self($parentName))->build()
            : new ClassDefinition(name: '');

        $classDefinition = new ClassDefinition(
            name: $this->name,
            templateTypes: $parentDefinition->templateTypes,
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition->properties ?: []),
            methods: $parentDefinition->methods ?: [],
            parentFqn: $parentName ?? null,
            parentClassDefinition: $parentDefinition->name ? $parentDefinition : null,
        );

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($classReflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $this->name) {
                continue;
            }

            if ($reflectionProperty->isStatic()) {
                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                        : new UnknownType,
                );
            } else {
                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $t = new TemplateType('T'.Str::studly($reflectionProperty->name)),
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                        : null,
                );
                $classDefinition->templateTypes[] = $t;
            }
        }

        foreach ($classReflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->class !== $this->name) {
                continue;
            }

            $classDefinition->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                (new FunctionLikeReflectionDefinitionBuilder($reflectionMethod->name, $reflectionMethod))->build()->getType(),
                definingClassName: $this->name,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        return $classDefinition;
    }
}

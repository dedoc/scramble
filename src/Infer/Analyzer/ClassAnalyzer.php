<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use ReflectionClass;

class ClassAnalyzer
{
    public function __construct(private Index $index) {}

    /**
     * @throws \ReflectionException
     */
    public function analyze(string $name): ClassDefinition
    {
        $classReflection = new ReflectionClass($name); // @phpstan-ignore argument.type

        $parentName = ($classReflection->getParentClass() ?: null)?->name;

        $parentDefinition = $parentName ? $this->index->getClass($parentName) : null;

        /*
         * @todo consider more advanced cloning implementation.
         * Currently just cloning property definition feels alright as only its `defaultType` may change.
         */
        $classDefinition = new ClassDefinition(
            name: $name,
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition?->properties ?: []),
            methods: array_map(fn ($md) => $md->copyFromParent(), $parentDefinition?->methods ?: []),
            parentFqn: $parentName,
        );

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($classReflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $name) {
                continue;
            }

            if ($reflectionProperty->isStatic()) {
                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                        : new UnknownType,
                );
            } else {
                $expectedTemplateTypeName = 'T'.Str::studly($reflectionProperty->name);

                $existingPropertyTemplateType = collect($classDefinition->templateTypes)
                    ->first(fn (TemplateType $t) => $t->name === $expectedTemplateTypeName);

                $propertyTemplateType = $existingPropertyTemplateType ?: new TemplateType(
                    $expectedTemplateTypeName,
                    is: ($reflectionPropertyType = $reflectionProperty->getType()) ? TypeHelper::createTypeFromReflectionType($reflectionPropertyType) : new UnknownType,
                );

                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $propertyTemplateType,
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? PropertyAnalyzer::from($reflectionProperty)->getDefaultType()
                        : null,
                );

                if (! $existingPropertyTemplateType) {
                    $classDefinition->templateTypes[] = $propertyTemplateType;
                }
            }
        }

        foreach ($classReflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->class !== $name) {
                continue;
            }

            $classDefinition->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                new FunctionType(
                    $reflectionMethod->name,
                    arguments: [],
                    returnType: new UnknownType,
                ),
                definingClassName: $name,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        $this->index->registerClassDefinition($classDefinition);

        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($classDefinition->name, $classDefinition));

        return $classDefinition;
    }
}

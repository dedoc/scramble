<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;

class ClassAnalyzer
{
    public function __construct(private Index $index)
    {
    }

    public function analyze(string $name): ClassDefinition
    {
        if ($definition = $this->index->getClassDefinition($name)) {
            return $definition;
        }

        $classReflection = new \ReflectionClass($name);

        $parentDefinition = null;
        if ($classReflection->getParentClass() && ! str_contains($classReflection->getParentClass()->getFileName(), '/vendor/')) {
            $parentDefinition = $this->analyze($parentName = $classReflection->getParentClass()->name);
        }

        $classDefinition = new ClassDefinition(
            name: $name,
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: $parentDefinition?->properties ?: [],
            methods: $parentDefinition?->methods ?: [],
            parentFqn: $parentName ?? null,
        );

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($classReflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $name) {
                continue;
            }

            $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                type: $t = new TemplateType('T'.Str::studly($reflectionProperty->name)),
                defaultType: $reflectionProperty->hasDefaultValue()
                    ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                    : null,
            );

            $classDefinition->templateTypes[] = $t;
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
                )
            );
        }

        $this->index->registerClassDefinition($classDefinition);

        return $classDefinition;
    }
}

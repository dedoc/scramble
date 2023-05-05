<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;

class ClassAnalyzer
{
    public function __construct(private ProjectAnalyzer $projectAnalyzer)
    {
    }

    public function analyze(string $name): ?ClassDefinition
    {
        if ($definition = $this->projectAnalyzer->index->getClassDefinition($name)) {
            return $definition;
        }

        try {
            $classReflection = new \ReflectionClass($name);
        } catch (\ReflectionException) {
            return null;
        }

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

        foreach ($classReflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $name) {
                continue;
            }

            $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                type: new TemplateType('T'.Str::studly($reflectionProperty->name)),
            );
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

        $this->projectAnalyzer->index->registerClassDefinition($classDefinition);

        return $classDefinition;
    }
}

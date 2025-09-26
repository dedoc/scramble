<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;

class LazyShallowClassDefinition extends ClassDefinition
{
    /**
     * @param  array<string, Type>  $parentDefinedTemplates
     * @param  array<string, array<string, Type>>  $mixinsDefinedTemplates
     * @param  array<string, array<string, Type>>  $interfacesDefinedTemplates
     */
    public array $parentDefinedTemplates = [];

    public array $mixinsDefinedTemplates = [];

    public array $interfacesDefinedTemplates = [];

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        if (! isset($this->methods[$name])) {
            return null;
        }

        if (isset($this->methods[$name]) && $this->methods[$name]->isFullyAnalyzed) {
            return $this->methods[$name];
        }

        $methodDefinition = $this->methods[$name];

        if (! $methodDefinition->definingClassName) {
            return null;
        }

        try {
            $reflection = (new ReflectionClass($methodDefinition->definingClassName))->getMethod($methodDefinition->type->name);
        } catch (ReflectionException) {
            return null;
        }

        /** @var Collection<string, Type> $definedTemplates */
        $definedTemplates = collect($this->templateTypes)->keyBy('name');

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $reflection,
            $definedTemplates
                ->merge($this->parentDefinedTemplates)
                ->merge($this->mixinsDefinedTemplates[$methodDefinition->definingClassName] ?? [])
                ->merge($this->interfacesDefinedTemplates[$methodDefinition->definingClassName] ?? []),
        ))->build();
    }

    public function getData(): ClassDefinitionData
    {
        return $this;
    }
}

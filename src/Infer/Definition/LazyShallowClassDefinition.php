<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;
use ReflectionException;

class LazyShallowClassDefinition implements ClassDefinitionContract
{
    /**
     * @param ClassDefinitionContract $definition
     * @param array<string, Type> $parentDefinedTemplates
     * @param array<string, array<string, Type>> $mixinsDefinedTemplates
     * @param array<string, array<string, Type>> $interfacesDefinedTemplates
     */
    public function __construct(
        public ClassDefinitionContract $definition,
        public array $parentDefinedTemplates = [],
        public array $mixinsDefinedTemplates = [],
        public array $interfacesDefinedTemplates = [],
    ) {}

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        $data = $this->getData();

        if (isset($data->methods[$name]) && $data->methods[$name]->isFullyAnalyzed) {
            return $data->methods[$name];
        }

        $methodDefinition = $data->methods[$name];

        try {
            $reflection = (new ReflectionClass($methodDefinition->definingClassName))->getMethod($methodDefinition->type->name);
        } catch (ReflectionException) {
            return null;
        }

        return $data->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $reflection,
            collect($data->templateTypes)
                ->keyBy('name')
                ->merge($this->parentDefinedTemplates)
                ->merge($this->mixinsDefinedTemplates[$methodDefinition->definingClassName] ?? [])
                ->merge($this->interfacesDefinedTemplates[$methodDefinition->definingClassName] ?? []),
        ))->build();
    }

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }
}

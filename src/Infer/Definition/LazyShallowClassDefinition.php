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
     * @param array<string, Type>[] $interfacesDefinedTemplates
     */
    public function __construct(
        public ClassDefinitionContract $definition,
        public array $parentDefinedTemplates = [],
        public array $interfacesDefinedTemplates = [],
    ) {}

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        $data = $this->getData();

        if (isset($data->methods[$name]) && $data->methods[$name]->isFullyAnalyzed) {
            return $data->methods[$name];
        }

        try {
            $reflection = (new ReflectionClass($data->name))->getMethod($name);
        } catch (ReflectionException) {
            return null;
        }

        return $data->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $reflection,
            collect($data->templateTypes)->keyBy('name')->merge($this->parentDefinedTemplates),
        ))->build();
    }

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }
}

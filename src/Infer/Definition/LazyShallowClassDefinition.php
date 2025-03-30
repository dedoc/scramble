<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use ReflectionClass;
use ReflectionException;

class LazyShallowClassDefinition implements ClassDefinitionContract
{
    public function __construct(
        public ClassDefinitionContract $definition,
    ) {}

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        $data = $this->getData();

        if (isset($data->methods[$name])) {
            return $data->methods[$name];
        }

        try {
            $reflection = (new ReflectionClass($data->name))->getMethod($name);
        } catch (ReflectionException) {
            return null;
        }

        return $data->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder($name, $reflection))->build();
    }

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }
}

<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;

class LazyClassDefinition extends ClassDefinition
{
    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->methods)) {
            return parent::getMethod($name);
        }

        if (! $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false)) {
            return null;
        }
        /** @var \ReflectionClass $classReflection */
        if (! $methodReflection = rescue(fn () => $classReflection->getMethod($name), report: false)) {
            return null;
        }
        /** @var \ReflectionMethod $methodReflection */

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
        ))->build();
    }
}

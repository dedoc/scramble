<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\LazyClassReflectionDefinitionBuilder;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

/**
 * This index stores lazily built definitions of classes and functions. These are extremely light definitions
 * that initially store only the name of the symbol. Then, only upon the request, the definition will build
 * itself (hence lazy). This helps to avoid keeping definition in memory that will never be used.
 */
class LazyShallowReflectionIndex implements IndexContract
{
    /**
     * @param  array<string, ClassDefinitionContract>  $classes
     * @param  array<string, FunctionLikeDefinition>  $functions
     */
    public function __construct(
        public array $classes = [],
        public array $functions = [],
    ) {}

    public function getClass(string $name): ?ClassDefinitionContract
    {
        if (isset($this->classes[$name])) {
            return $this->classes[$name];
        }

        try {
            $reflection = new ReflectionClass($name);
        } catch (ReflectionException) {
            return null;
        }

        return $this->classes[$name] = (new LazyClassReflectionDefinitionBuilder($this, $reflection))->build();
    }

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }

        try {
            $reflection = new ReflectionFunction($name);
        } catch (ReflectionException) {
            return null;
        }

        return $this->functions[$name] = (new FunctionLikeReflectionDefinitionBuilder($name, $reflection))->build();
    }
}

<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Scramble;
use ReflectionClass;

/**
 * This class definition combines the behavior of class' definitions created by both AST
 * definition builder and by lazy shallow class definition.
 *
 * Some part of this class definition may be created by the lazy shallow reflection definition builder
 * which doesn't analyze method annotation till the moment when the method definition is requested. And
 * other part of this definition may be built by AST definition builder which also has its own methods analysis
 * behavior: initially the list of methods is stored, but the method AST isn't analyzed till the very last
 * moment when method definition is requests.
 *
 * So summarizing, LazyClassDefinition will combine both of these behaviors: AST behavior is for methods of classes
 * that are not the vendor ones and LazyShallowClassDefinition behavior is for methods of classes that are the part
 * of the vendor.
 *
 * For example, if there is a concrete model class that is the part of the app, it's incomplete methods will be the
 * part of the LazyClassDefinition, and the methods of the parent class will also be available when requested.
 *
 * @todo how interfaces are handled!?
 */
class LazyClassDefinition extends ClassDefinition
{
    public function getMethod(string $name, array $indexBuilders = [], bool $withSideEffects = false): ?FunctionLikeDefinition
    {
        if (! isset($this->methods[$name])) {
            return null;
        }

        if ($this->methods[$name]->isFullyAnalyzed) {
            return $this->methods[$name];
        }

        $reflectionMethod = (new ReflectionClass($this->methods[$name]->definingClassName))->getMethod($name); // @phpstan-ignore argument.type

        if (Scramble::infer()->config->shouldAnalyzeAst($reflectionMethod->class)) {
            return $this->methods[$name] = $this->getMethodDefinition($name, indexBuilders: $indexBuilders, withSideEffects: $withSideEffects);
        }

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $reflectionMethod,
            collect($this->templateTypes)->keyBy('name'),
        ))->build();

        dd(dd($res)->type->toString());

        return $res;
    }
}

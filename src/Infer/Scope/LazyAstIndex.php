<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\LazyAstClassDefinitionBuilder;

/**
 * This index builds definitions of symbols upon explicit request by analyzing source code.
 */
class LazyAstIndex implements IndexContract
{
    /**
     * @param  array<string, ClassDefinitionContract>  $classes
     * @param  array<string, FunctionLikeDefinition>  $functions
     */
    public function __construct(
        public array $classes = [],
        public array $functions = [],
    ) {}

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }
    }

    public function getClass(string $name): ?ClassDefinitionContract
    {
        if (isset($this->classes[$name])) {
            return $this->classes[$name];
        }

        return $this->classes[$name] ??= (new LazyAstClassDefinitionBuilder($this, $name))->build();
    }
}

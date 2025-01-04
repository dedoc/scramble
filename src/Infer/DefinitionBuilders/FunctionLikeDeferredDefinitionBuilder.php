<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\Definition;
use Dedoc\Scramble\Infer\Contracts\DefinitionBuilder;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;
use PhpParser\Parser;

class FunctionLikeDeferredDefinitionBuilder implements DefinitionBuilder
{
    public function __construct(
        public readonly ReflectionFunction $reflectionFunction,
        public readonly Parser $parser,
    )
    {
    }

    public function build(): Definition
    {
        $shallowDefinitionBuilder = $this->reflectionFunction->sourceLocator instanceof ReflectionSourceLocator
            ? new FunctionLikeReflectionDefinitionBuilder($this->reflectionFunction)
            : new FunctionLikeShallowAstDefinitionBuilder($this->reflectionFunction, $this->parser);

        return new FunctionLikeDeferredDefinition(
            definition: $shallowDefinitionBuilder->build(),
            builder: new FunctionLikeAstDefinitionBuilder($this->reflectionFunction, $this->parser),
            index: $this->reflectionFunction->index,
        );
    }
}

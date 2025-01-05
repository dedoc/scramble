<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\DefinitionBuilder;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;

class FunctionLikeDeferredDefinitionBuilder implements DefinitionBuilder
{
    public function __construct(
        public readonly string $name,
        public readonly AstLocator $astLocator,
    ) {}

    public function build(): FunctionLikeDeferredDefinition
    {
        $shallowDefinitionBuilder = $this->astLocator->sourceLocator instanceof ReflectionSourceLocator
            ? new FunctionLikeReflectionDefinitionBuilder($this->name)
            : new FunctionLikeShallowAstDefinitionBuilder($this->name, $this->astLocator);

        return new FunctionLikeDeferredDefinition(
            definition: $shallowDefinitionBuilder->build(),
            builder: new FunctionLikeAstDefinitionBuilder($this->name, $this->astLocator),
        );
    }
}

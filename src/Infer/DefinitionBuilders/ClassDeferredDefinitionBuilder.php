<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;

class ClassDeferredDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        private string $name,
        private AstLocator $astLocator,
    ) {}

    public function build(): ClassDefinitionContract
    {
        $shallowDefinitionBuilder = $this->astLocator->sourceLocator instanceof ReflectionSourceLocator
            ? new ClassReflectionDefinitionBuilder($this->name)
            : new ClassShallowAstDefinitionBuilder($this->name, $this->astLocator);

        return new ClassDeferredDefinition(
            definition: $shallowDefinitionBuilder->build(),
            builder: new ClassAstDefinitionBuilder($this->name, $this->astLocator),
            astLocator: $this->astLocator,
        );
    }
}

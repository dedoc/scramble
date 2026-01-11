<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\DefinitionBuilders\SelfOutTypeBuilder;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;

class FunctionLikeDefinition
{
    public bool $isFullyAnalyzed = false;

    public bool $referencesResolved = false;

    private ?Generic $selfOutType;

    /**
     * @param  array<string, Type>  $argumentsDefaults  A map where the key is arg name and value is a default type.
     */
    public function __construct(
        public FunctionType $type,
        public array $argumentsDefaults = [],
        public ?string $definingClassName = null,
        public bool $isStatic = false,
        public ?SelfOutTypeBuilder $selfOutTypeBuilder = null,
    ) {}

    public function isFullyAnalyzed(): bool
    {
        return $this->isFullyAnalyzed;
    }

    public function addArgumentDefault(string $paramName, Type $type): self
    {
        $this->argumentsDefaults[$paramName] = $type;

        return $this;
    }

    public function getSelfOutType(): ?Generic
    {
        return $this->selfOutType ??= $this->selfOutTypeBuilder?->build();
    }

    public function getReturnType(): Type
    {
        return $this->type->getReturnType();
    }

    /**
     * When analyzing parent classes, function like definitions are "copied" from parent class. When function
     * like is sourced from AST, we don't want to make a deep clone to save some memory (is the difference really makes sense?)
     * as the definition will be re-build to the specifics of a given class. However, other types of fn definitions
     * may override this method and do a deeper cloning (or cloning at all!).
     */
    public function copyFromParent(): self
    {
        return $this;
    }
}

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

    private ?Generic $_selfOutType;

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
        return $this->_selfOutType ??= $this->selfOutTypeBuilder?->build();
    }
}

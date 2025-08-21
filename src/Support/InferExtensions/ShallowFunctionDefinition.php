<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;

class ShallowFunctionDefinition extends FunctionLikeDefinition
{
    public function __construct(
        public FunctionType $type,
        public array $argumentsDefaults = [],
        public ?string $definingClassName = null,
        public bool $isStatic = false,
        public ?Generic $selfOutType = null,
    ) {
        parent::__construct($this->type, $this->argumentsDefaults, $this->definingClassName, $this->isStatic);
        $this->isFullyAnalyzed = true;
    }

    public function getSelfOutType(): ?Generic
    {
        return $this->selfOutType;
    }

    public function copyFromParent(): self
    {
        $copy = clone $this;
        $copy->type = $copy->type->clone();

        return $copy;
    }
}

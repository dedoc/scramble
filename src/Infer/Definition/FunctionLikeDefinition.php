<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\FunctionType;

class FunctionLikeDefinition
{
    public bool $isFullyAnalyzed = false;

    public function __construct(
        public FunctionType $type,
        public array $sideEffects = [],
    ) {
    }

    public function isFullyAnalyzed(): bool
    {
        return $this->isFullyAnalyzed;
    }
}

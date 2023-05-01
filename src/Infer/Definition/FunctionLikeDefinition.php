<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\FunctionType;

class FunctionLikeDefinition
{
    public function __construct(
        public FunctionType $type,
        public array $sideEffects = [],
    ) {
    }
}

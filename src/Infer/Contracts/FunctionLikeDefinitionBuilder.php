<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;

interface FunctionLikeDefinitionBuilder
{
    public function build(): FunctionLikeDefinition;
}

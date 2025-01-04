<?php

namespace Dedoc\Scramble\Infer\Contracts;

interface FunctionLikeDefinitionBuilder extends DefinitionBuilder
{
    public function build(): FunctionLikeDefinition;
}

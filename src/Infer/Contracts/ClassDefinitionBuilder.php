<?php

namespace Dedoc\Scramble\Infer\Contracts;

interface ClassDefinitionBuilder extends DefinitionBuilder
{
    public function build(): ClassDefinition;
}

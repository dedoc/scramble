<?php

namespace Dedoc\Scramble\Infer\Contracts;

interface ClassDefinitionBuilder
{
    public function build(): ClassDefinition;
}

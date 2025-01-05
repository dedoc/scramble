<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;

interface ClassDefinition extends Definition
{
    public function getMethod(string $name): ?FunctionLikeDefinition;

    public function getData(): ClassDefinitionData;
}

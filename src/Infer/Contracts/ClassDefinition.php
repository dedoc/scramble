<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;

interface ClassDefinition
{
    public function getMethod(string $name): ?FunctionLikeDefinition;

    public function getData(): ClassDefinitionData;
}

<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;

interface ClassDefinition
{
    public function getMethod(string $name): ?FunctionLikeDefinition;

    public function getData(): ClassDefinitionData;
}

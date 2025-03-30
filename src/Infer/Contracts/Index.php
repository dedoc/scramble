<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;

interface Index
{
    public function getFunction(string $name): ?FunctionLikeDefinition;

    public function getClass(string $name): ?ClassDefinitionContract;
}

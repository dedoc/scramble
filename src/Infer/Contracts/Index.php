<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;

interface Index
{
    /**
     * @todo What should be really returned here???
     */
    public function getFunction(string $name): ?FunctionType;

    public function getClass(string $name): ?ClassDefinition;
}

<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Scope\Index;

class Infer
{
    public function __construct(
        public Index $index
    ) {}

    public function analyzeClass(string $class): ClassDefinitionContract
    {
        if (! $classDefinition = $this->index->getClass($class)) {
            throw new \RuntimeException("Definition of $class is not found in index");
        }

        return $classDefinition;
    }
}

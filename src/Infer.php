<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Index;

class Infer
{
    public function __construct(
        public Index $index
    ) {
    }

    public function analyzeClass(string $class): ClassDefinition
    {
        if (! $this->index->getClassDefinition($class)) {
            $this->index->registerClassDefinition(
                (new ClassAnalyzer($this->index))->analyze($class)
            );
        }

        return $this->index->getClassDefinition($class);
    }
}

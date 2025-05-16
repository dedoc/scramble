<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
// use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyAstIndex;

class Infer
{
    public function __construct(
        public LazyAstIndex $index
    ) {}

    public function analyzeClass(string $class): ClassDefinition
    {
        //        if (! $this->index->getClass($class)) {
        //            $this->index->registerClassDefinition(
        //                (new ClassAnalyzer($this->index))->analyze($class)
        //            );
        //        }

        return $this->index->getClass($class);
    }
}

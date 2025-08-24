<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Configuration\InferConfig;
use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Index;

class Infer
{
    public function __construct(
        public Index $index,
        public InferConfig $config,
    ) {}

    public function analyzeClass(string $class): ClassDefinition
    {
        if (! $this->index->getClassDefinition($class)) {
            $this->index->registerClassDefinition(
                (new ClassAnalyzer($this->index))->analyze($class)
            );
        }

        return $this->index->getClassDefinition($class);
    }

    public function configure(): InferConfig
    {
        return $this->config;
    }
}

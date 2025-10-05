<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileNameResolver;

class MethodAnalyzer
{
    public function __construct(
        private Index $index,
        private ClassDefinition $classDefinition,
    ) {}

    public function analyze(FunctionLikeDefinition $methodDefinition, array $indexBuilders = [], bool $withSideEffects = false)
    {
        try {
            $node = $this->getClassReflector()->getMethod($methodDefinition->type->name)->getAstNode();
        } catch (\ReflectionException) {
            return null;
        }

        if (! $node) {
            return $methodDefinition;
        }

        return $this->classDefinition->methods[$methodDefinition->type->name] = (new FunctionLikeAstDefinitionBuilder(
            $methodDefinition->type->name,
            $node,
            $this->index,
            new FileNameResolver($this->getClassReflector()->getNameContext()),
            $this->classDefinition,
            $indexBuilders,
            $withSideEffects
        ))->build();
    }

    private function getClassReflector(): ClassReflector
    {
        return ClassReflector::make($this->classDefinition->name);
    }
}

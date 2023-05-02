<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Infer class is a convenient wrapper around Infer\ProjectAnalyzer class that allows
 * to get type information about AST on demand in a sane manner.
 */
class Infer
{
    private ReferenceTypeResolver $referenceTypeResolver;

    public function __construct(private ProjectAnalyzer $projectAnalyzer)
    {
        $this->referenceTypeResolver = new ReferenceTypeResolver($projectAnalyzer->index);
    }

    public function getIndex(): Index
    {
        return $this->projectAnalyzer->index;
    }

    public function analyzeClass(string $class): ClassDefinition
    {
        if (! $this->getIndex()->getClassDefinition($class)) {
            $this->projectAnalyzer
                ->addFile((new \ReflectionClass($class))->getFileName())
                ->analyze();
        }

        return $this->getIndex()->getClassDefinition($class);
    }

    public function getMethodCallType(Type $calledOn, string $methodName, array $arguments = [])
    {

    }

    public function getPropertyFetchType(Type $calledOn, string $propertyName)
    {

    }
}

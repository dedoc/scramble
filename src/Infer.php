<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceResolutionOptions;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;

/**
 * Infer class is a convenient wrapper around Infer\ProjectAnalyzer class that allows
 * to get type information about AST on demand in a sane manner.
 */
class Infer
{
    private ReferenceTypeResolver $referenceTypeResolver;

    public function __construct(private ProjectAnalyzer $projectAnalyzer)
    {
        $this->referenceTypeResolver = new ReferenceTypeResolver(
            $projectAnalyzer->index,
            ReferenceResolutionOptions::make()
                ->resolveUnknownClassesUsing(function (string $name) {
                    if (! class_exists($name)) {
                        return null;
                    }

                    $path = (new \ReflectionClass($name))->getFileName();

                    if (str_contains($path, '/vendor/')) {
                        return null;
                    }
                    // dump(['unknownClassHandler' => $name]);

                    (new ClassAnalyzer($this->projectAnalyzer))->analyze($name);

                    return $this->getIndex()->getClassDefinition($name);
                })
                ->resolveResultingReferencesIntoUnknown(true)
        );
    }

    public function getIndex(): Index
    {
        return $this->projectAnalyzer->index;
    }

    public function analyzeClass(string $class, $methodsToResolve = null): ClassDefinition
    {
        if (! $this->getIndex()->getClassDefinition($class)) {
            $this->getIndex()->registerClassDefinition(
                (new ClassAnalyzer($this->projectAnalyzer))->analyze($class)
            );
        }

        return $this->getIndex()
            ->getClassDefinition($class)
            ->setReferenceTypeResolver($this->referenceTypeResolver);
    }
}

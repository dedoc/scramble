<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceResolutionOptions;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

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
                    // dump(['unknownClassHandler' => $name]);
                    if (!class_exists($name)) {
                        return null;
                    }

                    $path = (new \ReflectionClass($name))->getFileName();

                    if (str_contains($path, '/vendor/')) {
                        return null;
                    }

                    $this->projectAnalyzer->addFile($path)->analyze();

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
            $this->projectAnalyzer
                ->addFile((new \ReflectionClass($class))->getFileName())
                ->analyze();
        }

        $definition = $this->getIndex()->getClassDefinition($class);

        foreach ($definition->methods as $name => $methodDefinition) {
            if ($methodsToResolve !== null && !in_array($name, $methodsToResolve)) {
                continue;
            }

            $references = (new TypeWalker)->find(
                $returnType = $methodDefinition->type->getReturnType(),
                fn ($t) => $t instanceof AbstractReferenceType,
            );

            $methodScope = new Scope(
                $this->getIndex(),
                new NodeTypesResolver,
                new ScopeContext($definition, $methodDefinition),
                new FileNameResolver(new NameContext(new Throwing())),
            );

            $returnType = $references
                ? $this->referenceTypeResolver
                    ->resolve($methodScope, $returnType)
                    ->mergeAttributes($returnType->attributes())
                : $returnType;

            $methodDefinition->type->setReturnType($returnType);
        }

        return $definition;
    }

    public function getMethodCallType(Type $calledOn, string $methodName, array $arguments = [])
    {

    }

    public function getPropertyFetchType(Type $calledOn, string $propertyName)
    {

    }
}

<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Illuminate\Support\Arr;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class MethodAnalyzer
{
    public function __construct(
        private ProjectAnalyzer $projectAnalyzer,
        private ClassDefinition $classDefinition,
    ) {
    }

    public function analyze(FunctionLikeDefinition $methodDefinition)
    {
        // dump("analyze {$this->classDefinition->name}@{$methodDefinition->type->name}");
        $methodReflection = $this->classDefinition
            ->getReflection()
            ->getMethod($methodDefinition->type->name);

        $this->traverseClassMethod(
            [$this->getClassReflector()->getMethod($methodDefinition->type->name)->getAstNode()],
            $methodDefinition,
        );

        $methodDefinition = $this->projectAnalyzer->index
            ->getClassDefinition($this->classDefinition->name)
            ->methods[$methodDefinition->type->name];

        $methodDefinition->isFullyAnalyzed = true;

        return $methodDefinition;
    }

    private function getClassReflector(): ClassReflector
    {
        return ClassReflector::make($this->classDefinition->name);
    }

    private function traverseClassMethod(array $nodes, FunctionLikeDefinition &$methodDefinition)
    {
        $traverser = new NodeTraverser;

        $nameResolver = new FileNameResolver($this->getClassReflector()->getNameContext());

        $traverser->addVisitor(new TypeInferer(
            $this->projectAnalyzer,
            $this->projectAnalyzer->extensions,
            $this->projectAnalyzer->handlers,
            $this->projectAnalyzer->index,
            $nameResolver,
            new Scope($this->projectAnalyzer->index, new NodeTypesResolver(), new ScopeContext($this->classDefinition), $nameResolver)
        ));

        $node = (new NodeFinder())
            ->findFirst(
                $nodes,
                fn ($n) => $n instanceof ClassMethod && $n->name->toString() === $methodDefinition->type->name
            );

        $traverser->traverse(Arr::wrap($node));

        return $node;
    }
}

<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Illuminate\Support\Arr;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

class MethodAnalyzer
{
    public function __construct(
        private ProjectAnalyzer $projectAnalyzer,
        private ClassDefinition $classDefinition,
    )
    {
    }

    public function analyze(FunctionLikeDefinition $methodDefinition)
    {
        $methodReflection = $this->classDefinition
            ->getReflection()
            ->getMethod($methodDefinition->type->name);

        $className = $this->classDefinition->getReflection()->getShortName();

        $methodContent = implode("\n", array_slice(
            file($methodReflection->getFileName()),
            $methodReflection->getStartLine() - 1,
            max($methodReflection->getEndLine() - $methodReflection->getStartLine(), 1) + 1,
        ));

        $partialClass = "<?php\nclass $className {\n$methodContent\n}";

        $this->traverseClassMethod(
            $this->projectAnalyzer->parser->parseContentNew($partialClass)->getStatements(),
            $methodDefinition,
        );

        $methodDefinition->isFullyAnalyzed = true;
    }

    private function traverseClassMethod(array $nodes, FunctionLikeDefinition $methodDefinition)
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new TypeInferer(
            $this->projectAnalyzer,
            $this->projectAnalyzer->extensions,
            $this->projectAnalyzer->handlers,
            $this->projectAnalyzer->index,
            $nr = new FileNameResolver(new NameContext(new Throwing())),
            new Scope($this->projectAnalyzer->index, new NodeTypesResolver(), new ScopeContext($this->classDefinition), $nr)
        ));

        $traverser->traverse(Arr::wrap(
            (new NodeFinder())
                ->findFirst(
                    $nodes,
                    fn ($n) => $n instanceof ClassMethod && $n->name->toString() === $methodDefinition->type->name
                )
        ));
    }
}

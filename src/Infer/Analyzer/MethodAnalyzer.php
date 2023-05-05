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
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

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
        // dump("analyze {$methodDefinition->type->name}");

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

        $methodNode = $this->traverseClassMethod(
            $this->projectAnalyzer->parser->parseContentNew($partialClass)->getStatements(),
            $methodDefinition,
        );

        $methodDefinition = $this->projectAnalyzer->index
            ->getClassDefinition($this->classDefinition->name)
            ->methods[$methodDefinition->type->name];

        $methodDefinition->isFullyAnalyzed = true;

        return $methodDefinition;
    }

    private function traverseClassMethod(array $nodes, FunctionLikeDefinition &$methodDefinition)
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class ($this->getNameContext()) extends NameResolver {
            public function __construct($nameContext){parent::__construct();$this->nameContext = $nameContext;}
            public function beforeTraverse(array $nodes) { return null; }
        });
        $traverser->addVisitor(new PhpDocResolver(
            $nameResolver = new FileNameResolver($this->getNameContext()),
        ));

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

    private function getNameContext()
    {
        if (! $this->classDefinition->nameContext) {
            $shortName = $this->classDefinition->getReflection()->getShortName();

            $code = Str::before(
                file_get_contents($this->classDefinition->getReflection()->getFileName()),
                "class $shortName"
            );

            $re = '/(namespace|use) ([.\s\S]*?);/m';
            preg_match_all($re, $code, $matches);

            $code = "<?php\n".implode("\n", $matches[0]);

            $nodes = $this->projectAnalyzer->parser->parseContentNew($code)->getStatements();

            $traverser = new NodeTraverser;
            $traverser->addVisitor($nameResolver = new NameResolver);
            $traverser->traverse($nodes);

            $this->classDefinition->nameContext = $nameResolver->getNameContext();
        }

        return $this->classDefinition->nameContext;
    }
}

<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use ReflectionClass;

class Infer
{
    private FileParser $parser;

    private array $extensions;

    private array $handlers;

    private array $cache = [];

    public function __construct(FileParser $parser, array $extensions = [], array $handlers = [])
    {
        $this->parser = $parser;
        $this->extensions = $extensions;
        $this->handlers = $handlers;
    }

    public function analyzeClass(string $class): ObjectType
    {
        return $this->cache[$class] ??= $this->traverseClassAstAndInferType($class);
    }

    private function traverseClassAstAndInferType(string $class): ObjectType
    {
        $fileAst = $this->parser->parse((new ReflectionClass($class))->getFileName());

        $traverser = new NodeTraverser;
        $traverser->addVisitor($inferer = new TypeInferer($this->extensions, $this->handlers));
        $traverser->traverse($fileAst);

        $classAst = (new NodeFinder())->findFirst(
            $fileAst,
            fn (Node $node) => $node instanceof Node\Stmt\Class_
                && ($node->namespacedName ?? $node->name)->toString() === ltrim($class, '\\'),
        );

        return $inferer->scope->getType($classAst);
    }
}

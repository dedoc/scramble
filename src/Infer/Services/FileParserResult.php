<?php

namespace Dedoc\Scramble\Infer\Services;

use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;

class FileParserResult
{
    public function __construct(
        private array $statements,
        private FileNameResolver $nameResolver,
    ) {}

    public function getNameResolver()
    {
        return $this->nameResolver;
    }

    public function getStatements(): array
    {
        return $this->statements;
    }

    public function findFirstClass(string $class)
    {
        return (new NodeFinder)->findFirst(
            $this->getStatements(),
            fn (Node $node) => $node instanceof Node\Stmt\Class_
                && ($node->namespacedName ?? $node->name)->toString() === ltrim($class, '\\'),
        );
    }

    public function findMethod(string $classMethod)
    {
        [$class, $method] = explode('@', $classMethod);

        $classAst = $this->findFirstClass($class);

        return (new NodeFinder)
            ->findFirst(
                Arr::wrap($classAst),
                fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method,
            );
    }
}

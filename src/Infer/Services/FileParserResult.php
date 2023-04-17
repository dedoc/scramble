<?php

namespace Dedoc\Scramble\Infer\Services;

use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;

class FileParserResult
{
    private array $statements;

    private FileNameResolver $namesResolver;

    public function __construct(array $statements, FileNameResolver $namesResolver)
    {
        $this->statements = $statements;
        $this->namesResolver = $namesResolver;
    }

    public function getStatements(): array
    {
        return $this->statements;
    }

    public function getNamesResolver(): FileNameResolver
    {
        return $this->namesResolver;
    }

    public function findFirstClass(string $class)
    {
        return (new NodeFinder())->findFirst(
            $this->getStatements(),
            fn (Node $node) => $node instanceof Node\Stmt\Class_
                && ($node->namespacedName ?? $node->name)->toString() === ltrim($class, '\\'),
        );
    }

    public function findMethod(string $classMethod)
    {
        [$class, $method] = explode('@', $classMethod);

        $classAst = $this->findFirstClass($class);

        return (new NodeFinder())
            ->findFirst(
                Arr::wrap($classAst),
                fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method,
            );
    }
}

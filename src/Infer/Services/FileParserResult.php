<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

class FileParserResult
{
    private array $statements;

    /**
     * @var callable(string, string)
     */
    private $namesResolver;

    public function __construct(array $statements, callable $namesResolver)
    {
        $this->statements = $statements;
        $this->namesResolver = $namesResolver;
    }

    /**
     * @return array
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    /**
     * @return callable
     */
    public function getNamesResolver(): callable
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

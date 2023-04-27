<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser;
use PhpParser\Node;

class AnalysisResult
{
    private Scope $scope;

    /**
     * @var PhpParser\Node\Stmt[]
     */
    private array $ast;

    public function __construct(Scope $scope, array $ast)
    {
        $this->scope = $scope;
        $this->ast = $ast;
    }

    public function getClassType(string $className): ?ObjectType
    {
        $node = (new PhpParser\NodeFinder)->findFirst(
            $this->ast,
            fn (Node $node) => $node instanceof PhpParser\Node\Stmt\Class_
                && $node->name->toString() === $className
        );

        if (! $node) {
            return null;
        }

        return $this->scope->getType($node);
    }

    public function getVarType(string $varName, $line = INF)
    {
        return (new ReferenceTypeResolver($this->scope->index))->resolve($this->scope->getType(
            new Node\Expr\Variable($varName, [
                'startLine' => $line,
            ]),
        ));
    }

    public function getFunctionType(string $functionName): ?FunctionType
    {
        $node = (new PhpParser\NodeFinder)->findFirst(
            $this->ast,
            fn (Node $node) => $node instanceof PhpParser\Node\Stmt\Function_
                && $node->name->toString() === $functionName
        );

        if (! $node) {
            return null;
        }

        return $this->scope->getType($node);
    }

    public function getAst()
    {
        return $this->ast;
    }
}

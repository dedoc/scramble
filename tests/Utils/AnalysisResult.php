<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser;
use PhpParser\Node;
use PhpParser\NodeTraverser;

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
        return (new ReferenceTypeResolver($this->scope->index))->resolve($this->scope, $this->scope->getType(
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

    public function getClassDefinition(string $string): ?ClassDefinition
    {
        return $this->scope->index->getClassDefinition($string);
    }

    public function getExpressionType(string $code)
    {
        $code = '<?php $a = '.$code.';';

        $fileAst = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7)->parse($code);

        $index = $this->scope->index;
        $infer = app()->make(TypeInferer::class, [
            'namesResolver' => new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing())),
            'extensions' => [/*...$extensions, ...DefaultExtensions::infer()*/],
            'referenceTypeResolver' => new \Dedoc\Scramble\Infer\Services\ReferenceTypeResolver($index),
            'index' => $index,
        ]);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($infer);
        $traverser->traverse($fileAst);

        return (new self($infer->scope, $fileAst))->getVarType('a');
    }
}

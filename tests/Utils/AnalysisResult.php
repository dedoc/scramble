<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use PhpParser;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class AnalysisResult
{
    public function __construct(public Index $index) {}

    public function getClassDefinition(string $string): ?ClassDefinition
    {
        return $this->index->getClassDefinition($string);
    }

    public function getFunctionDefinition(string $string): ?FunctionLikeDefinition
    {
        return $this->index->getFunctionDefinition($string);
    }

    public function getExpressionType(string $code)
    {
        $code = '<?php $a = '.$code.';';

        $fileAst = (new PhpParser\ParserFactory)->createForHostVersion()->parse($code);

        $index = $this->index;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver = new NameResolver);
        $traverser->addVisitor(new PhpDocResolver(
            $nameResolver = new FileNameResolver($nameResolver->getNameContext()),
        ));
        $traverser->addVisitor(new TypeInferer(
            $index,
            $nameResolver,
            $scope = new Scope($index, new NodeTypesResolver, new ScopeContext, $nameResolver),
            Context::getInstance()->extensionsBroker->extensions,
        ));
        $traverser->traverse($fileAst);

        $unresolvedType = $scope->getType(
            new Node\Expr\Variable('a', [
                'startLine' => INF,
            ]),
        );

        return (new ReferenceTypeResolver($this->index))->resolve($scope, $unresolvedType);
    }
}

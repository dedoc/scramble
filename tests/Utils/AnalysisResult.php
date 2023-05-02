<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use PhpParser;
use PhpParser\Node;
use PhpParser\NodeTraverser;

class AnalysisResult
{
    public function __construct(public Index $index)
    {
    }

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

        $fileAst = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7)->parse($code);

        $index = $this->index;
        $infer = new TypeInferer(
            $projectAnalyzer = app()->make(ProjectAnalyzer::class, ['index' => $this->index]),
            [],
            [],
            $index,
        );
        $traverser = new NodeTraverser;
        $traverser->addVisitor($infer);
        $traverser->traverse($fileAst);

        return (new ReferenceTypeResolver($this->index))->resolve($infer->scope, $infer->scope->getType(
            new Node\Expr\Variable('a', [
                'startLine' => INF,
            ]),
        ));
    }
}

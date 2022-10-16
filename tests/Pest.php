<?php

use Dedoc\Scramble\Infer\TypeInferringVisitor;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory as ParserFactoryAlias;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(string $code): AnalysisResult
{
    $fileAst = (new ParserFactoryAlias)->create(ParserFactoryAlias::PREFER_PHP7)->parse($code);

    $infer = app()->make(TypeInferringVisitor::class, ['namesResolver' => fn ($s) => $s]);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($infer);
    $traverser->traverse($fileAst);

    return new AnalysisResult($infer->scope, $fileAst);
}

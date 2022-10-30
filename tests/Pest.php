<?php

use Dedoc\Scramble\DefaultExtensions;
use Dedoc\Scramble\Infer\TypeInferringVisitor;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory as ParserFactoryAlias;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(string $code, array $extensions = []): AnalysisResult
{
    $fileAst = (new ParserFactoryAlias)->create(ParserFactoryAlias::PREFER_PHP7)->parse($code);

    $infer = app()->make(TypeInferringVisitor::class, [
        'namesResolver' => fn ($s) => $s,
        'extensions' => [...$extensions, ...DefaultExtensions::infer()],
    ]);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($infer);
    $traverser->traverse($fileAst);

    return new AnalysisResult($infer->scope, $fileAst);
}

function getStatementType(string $statement): ?Type
{
    $code = <<<EOD
<?php
\$a = $statement;
EOD;

    return analyzeFile($code)->getVarType('a');
}

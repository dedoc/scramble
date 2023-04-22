<?php

use Dedoc\Scramble\DefaultExtensions;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use Illuminate\Routing\Route;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory as ParserFactoryAlias;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(string $code, array $extensions = []): AnalysisResult
{
    $fileAst = (new ParserFactoryAlias)->create(ParserFactoryAlias::PREFER_PHP7)->parse($code);

    $index = new Index();
    $infer = app()->make(TypeInferer::class, [
        'namesResolver' => new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing())),
        'extensions' => [...$extensions, ...DefaultExtensions::infer()],
        'referenceTypeResolver' => new \Dedoc\Scramble\Infer\Services\ReferenceTypeResolver($index),
        'index' => $index,
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

function generateForRoute(Closure $param)
{
    $route = $param();

    Scramble::routes(fn (Route $r) => $r->uri === $route->uri);

    return app()->make(\Dedoc\Scramble\Generator::class)();
}

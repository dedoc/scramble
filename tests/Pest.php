<?php

use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use Illuminate\Routing\Route;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\ParserFactory as ParserFactoryAlias;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(string $code, $extensions = [], bool $resolveReferences = true): AnalysisResult
{
    if ($code[0] === '/') {
        $code = file_get_contents($code);
    }

    $projectAnalyzer = new ProjectAnalyzer(
        parser: new FileParser((new ParserFactory)->create(ParserFactory::PREFER_PHP7)),
        extensions: $extensions,
    );

    $projectAnalyzer->addFile('virtual.php', $code);

    $projectAnalyzer->analyze();

    return new AnalysisResult($projectAnalyzer->index);

    $fileAst = (new ParserFactoryAlias)->create(ParserFactoryAlias::PREFER_PHP7)->parse($code);

    $index = new Index();
    $infer = app()->make(TypeInferer::class, [
        'namesResolver' => new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing())),
        'extensions' => [...$extensions/* ...DefaultExtensions::infer()*/],
        'referenceTypeResolver' => new \Dedoc\Scramble\Infer\Services\ReferenceTypeResolver($index),
        'index' => $index,
        'shouldResolveReferences' => $resolveReferences,
    ]);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($infer);
    $traverser->traverse($fileAst);

    return new AnalysisResult($infer->scope);
}

function getStatementType(string $statement): ?Type
{
    return analyzeFile('<?php')->getExpressionType($statement);
}

dataset('extendableTemplateTypes', [
    ['int', 'int'],
    ['bool', 'boolean'],
    ['float', 'float'],
    ['', 'TA', 'TA'],
    ['string', 'TA', 'TA is string'],
    ['SomeClass', 'TA', 'TA is SomeClass'],
    ['callable', 'TA', 'TA is callable'],
]);

function generateForRoute(Closure $param)
{
    $route = $param();

    Scramble::routes(fn (Route $r) => $r->uri === $route->uri);

    return app()->make(\Dedoc\Scramble\Generator::class)();
}


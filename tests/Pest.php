<?php

use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Services\ReferenceResolutionOptions;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use Illuminate\Routing\Route;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\ParserFactory;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(
    string $code,
    $extensions = [],
    bool $shouldResolveReferences = true,
): AnalysisResult
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

    if ($shouldResolveReferences) {
        $referenceResolver = new ReferenceTypeResolver(
            $projectAnalyzer->index,
            ReferenceResolutionOptions::make()
                ->resolveUnknownClassesUsing(fn () => null)
                ->resolveResultingReferencesIntoUnknown(true)
        );

        resolveReferences($projectAnalyzer, $referenceResolver);
    }

    return new AnalysisResult($projectAnalyzer->index);
}

function resolveReferences(ProjectAnalyzer $projectAnalyzer, ReferenceTypeResolver $referenceResolver)
{
    $resolveReferencesInFunctionReturn = function ($scope, $functionType) use ($referenceResolver) {
        if (! ReferenceTypeResolver::hasResolvableReferences($returnType = $functionType->getReturnType())) {
            return;
        }

        $resolvedReference = $referenceResolver->resolve($scope, $returnType);

        $functionType->setReturnType($resolvedReference);
    };

    foreach ($projectAnalyzer->index->functionsDefinitions as $functionDefinition) {
        $fnScope = new Scope(
            $projectAnalyzer->index,
            new NodeTypesResolver,
            new ScopeContext(functionDefinition: $functionDefinition),
            new FileNameResolver(new NameContext(new Throwing())),
        );
        $resolveReferencesInFunctionReturn($fnScope, $functionDefinition->type);
    }

    foreach ($projectAnalyzer->index->classesDefinitions as $classDefinition) {
        foreach ($classDefinition->methods as $name => $methodDefinition) {
            $methodScope = new Scope(
                $projectAnalyzer->index,
                new NodeTypesResolver,
                new ScopeContext($classDefinition, $methodDefinition),
                new FileNameResolver(new NameContext(new Throwing())),
            );
            $resolveReferencesInFunctionReturn($methodScope, $methodDefinition->type);
        }
    }
}

function getStatementType(string $statement, array $extensions = []): ?Type
{
    return analyzeFile('<?php', $extensions)->getExpressionType($statement);
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

<?php

use Dedoc\Scramble\Extensions;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Tests\TestCase;
use Dedoc\Scramble\Tests\Utils\AnalysisResult;
use Illuminate\Routing\Route;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

uses(TestCase::class)->in(__DIR__);

function analyzeFile(
    string $code,
    $extensions = [],
    bool $shouldResolveReferences = true,
): AnalysisResult {
    if ($code[0] === '/') {
        $code = file_get_contents($code);
    }

    $index = new Index;

    $traverser = new NodeTraverser;
    $traverser->addVisitor($nameResolver = new NameResolver());
    $traverser->addVisitor(new PhpDocResolver(
        $nameResolver = new FileNameResolver($nameResolver->getNameContext()),
    ));
    $traverser->addVisitor(new TypeInferer(
        Extensions::makeInferHandlers(),
        $index,
        $nameResolver,
        new Scope($index, new NodeTypesResolver(), new ScopeContext(), $nameResolver)
    ));
    $traverser->traverse(
        FileParser::getInstance()->parseContentNew($code)->getStatements(),
    );

    // Should this be here? Index must be global?
    resolveReferences($index, new ReferenceTypeResolver($index));

    return new AnalysisResult($index);
}

function analyzeClass(string $className): AnalysisResult
{
    $infer = app(Infer::class);

    $infer->analyzeClass($className);

    return new AnalysisResult($infer->index);
}

function resolveReferences(Index $index, ReferenceTypeResolver $referenceResolver)
{
    $resolveReferencesInFunctionReturn = function ($scope, $functionType) use ($referenceResolver) {
        if (! ReferenceTypeResolver::hasResolvableReferences($returnType = $functionType->getReturnType())) {
            return;
        }

        $resolvedReference = $referenceResolver->resolve($scope, $returnType);

        $functionType->setReturnType($resolvedReference);
    };

    foreach ($index->functionsDefinitions as $functionDefinition) {
        $fnScope = new Scope(
            $index,
            new NodeTypesResolver,
            new ScopeContext(functionDefinition: $functionDefinition),
            new FileNameResolver(new NameContext(new Throwing())),
        );
        $resolveReferencesInFunctionReturn($fnScope, $functionDefinition->type);
    }

    foreach ($index->classesDefinitions as $classDefinition) {
        foreach ($classDefinition->methods as $name => $methodDefinition) {
            $methodScope = new Scope(
                $index,
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

<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Tests\Infer\stubs\Foo;
use Dedoc\Scramble\Tests\Infer\stubs\Bar;
use PhpParser\ParserFactory;

beforeEach(closure: function () {
    $this->index = new Index;

    $this->app->singleton(ProjectAnalyzer::class, fn () => new ProjectAnalyzer(
        parser: new FileParser((new ParserFactory)->create(ParserFactory::PREFER_PHP7)),
        index: $this->index,
    ));

    $this->classAnalyzer = new ClassAnalyzer(app(ProjectAnalyzer::class));
});

it('creates a definition from the given class', function () {
    $definition = $this->classAnalyzer->analyze(Foo::class);

    expect($this->index->classesDefinitions)
        ->toHaveKeys([Foo::class, Bar::class])
        ->and($definition->methods)->toHaveKey('foo')
        ->and(($fooRawDef = $definition->methods['foo'])->isFullyAnalyzed())->toBeFalse()
        ->and($fooRawDef->type->getReturnType()->toString())->toBe('unknown');
});

it('resolves function return type after explicitly requested', function () {
    $fooDef = $this->classAnalyzer
        ->analyze(Foo::class)
        ->getMethodDefinition('foo');

    expect($fooDef->type->getReturnType()->toString())->toBe('int(243)');
});

it('resolves fully qualified names', function () {
    $fqnDef = $this->classAnalyzer
        ->analyze(Foo::class)
        ->getMethodDefinition('fqn');

    expect($fqnDef->type->getReturnType()->toString())->toBe('string('.Foo::class.')');
});

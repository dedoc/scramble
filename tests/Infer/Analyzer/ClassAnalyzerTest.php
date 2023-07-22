<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Tests\Infer\stubs\Bar;
use Dedoc\Scramble\Tests\Infer\stubs\Foo;
use Dedoc\Scramble\Tests\Infer\stubs\FooWithTrait;

beforeEach(function () {
    $this->index = app(Index::class);

    $this->classAnalyzer = new ClassAnalyzer($this->index);

    $this->resolver = new ReferenceTypeResolver($this->index);
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
        ->getMethodDefinition('bar');

    expect($fooDef->type->getReturnType()->toString())->toBe('int(243)');
});

it('resolves fully qualified names', function () {
    $fqnDef = $this->classAnalyzer
        ->analyze(Foo::class)
        ->getMethodDefinition('fqn');

    expect($fqnDef->type->getReturnType()->toString())->toBe('string('.Foo::class.')');
});

it('resolves pending returns lazily', function () {
    $classDefinition = $this->classAnalyzer->analyze(Foo::class);

    $barDef = $classDefinition->getMethodDefinition('bar');
    $barReturnType = $this->resolver->resolve(
        new Scope($this->index, new NodeTypesResolver(), new ScopeContext($classDefinition), new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing()))),
        $barDef->type->getReturnType(),
    );

    expect($barReturnType->toString())->toBe('int(243)');
});

it('analyzes traits', function () {
    $classDef = $this->classAnalyzer->analyze(FooWithTrait::class);

    expect($classDef->properties)->toHaveCount(1)->toHaveKeys([
        'propBaz',
    ]);
    expect($classDef->methods)->toHaveCount(3)->toHaveKeys([
        'something',
        'methodBaz',
        'methodInvokingFooTraitMethod',
    ]);
});

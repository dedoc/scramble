<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Support\Collection;

function buildAstFunctionDefinition(MethodReflector $reflector, ?ClassDefinition $classDefinition = null): FunctionLikeDefinition
{
    return (new FunctionLikeAstDefinitionBuilder(
        $reflector->name,
        $reflector->getAstNode(),
        app(Index::class),
        new FileNameResolver($reflector->getClassReflector()->getNameContext()),
        $classDefinition
    ))->build();
}

test('respects scramble-return primitive annotation', function () {
    $definition = buildAstFunctionDefinition(
        MethodReflector::make(Foo_FunctionLikeAstDefinitionBuilderTest::class, 'foo'),
    );

    expect($definition->type->returnType->toString())->toBe('int');
});

test('respects scramble-return generic annotation', function () {
    $definition = buildAstFunctionDefinition(
        MethodReflector::make(Foo_FunctionLikeAstDefinitionBuilderTest::class, 'bar'),
    );

    expect($definition->type->returnType->toString())->toBe('Illuminate\Support\Collection<int, string>');
});

test('allow templates in scramble-return annotation', function () {
    $definition = buildAstFunctionDefinition(
        MethodReflector::make(Bar_FunctionLikeAstDefinitionBuilderTest::class, 'templated'),
        new ClassDefinition(Bar_FunctionLikeAstDefinitionBuilderTest::class, [new TemplateType('TFoo')])
    );

    expect($rt = $definition->type->returnType)->toBeInstanceOf(Generic::class)
        ->and($rt->name)->toBe(Collection::class)
        ->and($t = $rt->templateTypes[1])->toBeInstanceOf(TemplateType::class)
        ->and($t->name)->toBe('TFoo');
});

class Foo_FunctionLikeAstDefinitionBuilderTest
{
    /**
     * @scramble-return int
     */
    public function foo() {}

    /**
     * @scramble-return \Illuminate\Support\Collection<int, string>
     */
    public function bar() {}

    /**
     * @scramble-return \Illuminate\Support\Collection<int, TNonExisting>
     */
    public function fail() {}
}

class Bar_FunctionLikeAstDefinitionBuilderTest
{
    /**
     * @scramble-return \Illuminate\Support\Collection<int, TFoo>
     */
    public function templated() {}
}

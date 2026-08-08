<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeDeclarationPhpDocDefinitionBuilder;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;

function buildPhpDocFunctionDefinition(
    string $docComment,
    FunctionType $type,
    ?ClassDefinition $classDefinition = null,
): FunctionLikeDefinition {
    return (new FunctionLikeDeclarationPhpDocDefinitionBuilder(
        new FunctionLikeDefinition($type),
        PhpDoc::parse($docComment),
        $classDefinition,
    ))->build();
}

test('applies return annotation', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @return int */',
        new FunctionType('foo'),
    );

    expect($definition->type->returnType->toString())->toBe('int');
});

test('registers template annotations', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @template TFoo */',
        new FunctionType('foo'),
    );

    expect($definition->type->templates)->toHaveCount(1)
        ->and($t = $definition->type->templates[0])->toBeInstanceOf(TemplateType::class)
        ->and($t->name)->toBe('TFoo');
});

test('resolves function templates in return annotation', function () {
    $definition = buildPhpDocFunctionDefinition(
        <<<'DOC'
        /**
         * @template TFoo
         * @return \Illuminate\Support\Collection<int, TFoo>
         */
        DOC,
        new FunctionType('foo'),
    );

    expect($rt = $definition->type->returnType)->toBeInstanceOf(Generic::class)
        ->and($rt->name)->toBe(Collection::class)
        ->and($t = $rt->templateTypes[1])->toBeInstanceOf(TemplateType::class)
        ->and($t->name)->toBe('TFoo');
});

test('resolves class templates in return annotation', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @return \Illuminate\Support\Collection<int, TFoo> */',
        new FunctionType('foo'),
        new ClassDefinition(Bar_FunctionLikeDeclarationPhpDocDefinitionBuilderTest::class, [new TemplateType('TFoo')]),
    );

    expect($rt = $definition->type->returnType)->toBeInstanceOf(Generic::class)
        ->and($rt->name)->toBe(Collection::class)
        ->and($t = $rt->templateTypes[1])->toBeInstanceOf(TemplateType::class)
        ->and($t->name)->toBe('TFoo');
});

test('appends throws annotations', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @throws \RuntimeException */',
        new FunctionType('foo'),
    );

    expect($definition->type->exceptions)->toHaveCount(1)
        ->and($definition->type->exceptions[0]->toString())->toBe('RuntimeException');
});

test('applies param annotation to a declared argument', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @param int $bar */',
        new FunctionType('foo', arguments: ['bar' => new UnknownType]),
    );

    expect($definition->type->arguments['bar']->toString())->toBe('int');
});

test('ignores param annotation for an undeclared argument', function () {
    $definition = buildPhpDocFunctionDefinition(
        '/** @param int $missing */',
        new FunctionType('foo'),
    );

    expect($definition->type->arguments)->toBe([]);
});

class Bar_FunctionLikeDeclarationPhpDocDefinitionBuilderTest {}

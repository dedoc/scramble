<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeDeclarationAstDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;

test('creates declaration definition from ast', function () {
    $reflector = MethodReflector::make(Foo_FunctionLikeDeclarationAstDefinitionBuilderTest::class, 'foo');

    $definition = (new FunctionLikeDeclarationAstDefinitionBuilder(
        $reflector->getAstNode(),
        new ClassDefinition(Foo_FunctionLikeDeclarationAstDefinitionBuilderTest::class),
    ))->build();

    expect($definition->type->toString())->toBe('(string): int')
        ->and($definition->type->name)->toBe('foo')
        ->and($definition->definingClassName)->toBe(Foo_FunctionLikeDeclarationAstDefinitionBuilderTest::class);
});

class Foo_FunctionLikeDeclarationAstDefinitionBuilderTest
{
    public function foo(string $b = 'foo'): int {}
}

<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\ClassReflectionDefinitionBuilder;

test('builds class definition', function () {
    $builder = new ClassReflectionDefinitionBuilder(Foo_ClassReflectionDefinitionBuilderTest::class);

    $definition = $builder->build();

    expect($definition->name)->toBe(Foo_ClassReflectionDefinitionBuilderTest::class)
        ->and($definition->templateTypes)->toHaveCount(1)
        ->and($definition->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->properties)->toHaveKey('foo')
        ->and($definition->properties['foo']->type)->toBe($definition->templateTypes[0])
        ->and($definition->methods)->toHaveKey('__construct')
        ->and($definition->methods['__construct']->getType()->arguments)->toHaveKey('foo');
});
class Foo_ClassReflectionDefinitionBuilderTest
{
    public string $foo;
    public function __construct(string $foo) {}
}

test('builds a parent class definition', function () {
    $builder = new ClassReflectionDefinitionBuilder(Bar_ClassReflectionDefinitionBuilderTest::class);

    $definition = $builder->build();

    expect($definition->parentFqn)->toBe(Foo_ClassReflectionDefinitionBuilderTest::class)
        ->and($definition->templateTypes)->toHaveCount(2)
        ->and($definition->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->templateTypes[1]->name)->toBe('TBar')
        ->and($definition->properties['bar']->type)->toBe($definition->templateTypes[1]);
});
class Bar_ClassReflectionDefinitionBuilderTest extends Foo_ClassReflectionDefinitionBuilderTest
{
    public string $bar;
    public function __construct(string $bar) {}
}

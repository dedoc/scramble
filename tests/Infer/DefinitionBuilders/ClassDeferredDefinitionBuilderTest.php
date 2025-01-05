<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\ClassDeferredDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use Dedoc\Scramble\Infer\SourceLocators\ReflectionSourceLocator;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory)->createForHostVersion();
    $this->index = new LazyIndex(parser: $this->parser);
});

test('builds class definition', function () {
    $builder = new ClassDeferredDefinitionBuilder(
        Foo_ClassDeferredDefinitionBuilderTest::class,
        new AstLocator($this->parser, new ReflectionSourceLocator()),
    );
    $definition = $builder->build();

    expect($definition->getData()->name)->toBe(Foo_ClassDeferredDefinitionBuilderTest::class)
        ->and($definition->getData()->templateTypes)->toHaveCount(1)
        ->and($definition->getData()->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->getData()->properties)->toHaveKey('foo')
        ->and($definition->getData()->properties['foo']->type)->toBe($definition->getData()->templateTypes[0])
        ->and($definition->getData()->methods)->toHaveKey('__construct')
        ->and($definition->getMethod('__construct')->getType()->arguments)->toHaveKey('foo');
});
class Foo_ClassDeferredDefinitionBuilderTest
{
    public string $foo;
    public function __construct(string $foo) {}
}

test('builds parent class definition', function () {
    $builder = new ClassDeferredDefinitionBuilder(
        Bar_ClassDeferredDefinitionBuilderTest::class,
        new AstLocator($this->parser, new ReflectionSourceLocator()),
    );
    $definition = $builder->build();

    expect($definition->getData()->parentFqn)->toBe(Foo_ClassDeferredDefinitionBuilderTest::class)
        ->and($definition->getData()->templateTypes)->toHaveCount(2)
        ->and($definition->getData()->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->getData()->templateTypes[1]->name)->toBe('TBar')
        ->and($definition->getData()->properties['bar']->type)->toBe($definition->getData()->templateTypes[1]);
});
class Bar_ClassDeferredDefinitionBuilderTest extends Foo_ClassDeferredDefinitionBuilderTest
{
    public string $bar;
    public function __construct(string $bar) {}
} 
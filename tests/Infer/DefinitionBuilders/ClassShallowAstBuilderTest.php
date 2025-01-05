<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\ClassShallowAstDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflection\ReflectionClass;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory)->createForHostVersion();
    $this->index = new LazyIndex(parser: $this->parser);
    $this->source = <<<'EOF'
<?php
class Bar extends Foo {
    public string $bar;
    public function __construct(string $bar) {}
}
class Foo {
    public string $foo;
    public function __construct(string $foo) {}
}
EOF;
});

test('builds class definition', function () {
    $reflection = ReflectionClass::createFromSource('Foo', $this->source, $this->index, $this->parser);

    $builder = new ClassShallowAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator));
    $definition = $builder->build();

    expect($definition->name)->toBe('Foo')
        ->and($definition->templateTypes)->toHaveCount(1)
        ->and($definition->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->properties)->toHaveKey('foo')
        ->and($definition->properties['foo']->type)->toBe($definition->templateTypes[0])
        ->and($definition->methods)->toHaveKey('__construct')
        ->and($definition->methods['__construct']->getType()->arguments)->toHaveKey('foo');
});

test('builds parent class definition', function () {
    $reflection = ReflectionClass::createFromSource('Bar', $this->source, $this->index, $this->parser);

    $builder = new ClassShallowAstDefinitionBuilder('Bar', new AstLocator($this->parser, $reflection->sourceLocator));
    $definition = $builder->build();

    expect($definition->parentFqn)->toBe('Foo')
        ->and($definition->templateTypes)->toHaveCount(2)
        ->and($definition->templateTypes[0]->name)->toBe('TFoo')
        ->and($definition->templateTypes[1]->name)->toBe('TBar')
        ->and($definition->properties['bar']->type)->toBe($definition->templateTypes[1]);
});

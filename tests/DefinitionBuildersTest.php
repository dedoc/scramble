<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeAutoResolvingDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassAstDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassAutoResolvingDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\ClassDeferredDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeShallowAstDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflection\ReflectionClass;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use Dedoc\Scramble\Infer\SourceLocators\AstLocator;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory())->createForHostVersion();
    $this->index = new LazyIndex(parser: $this->parser);
});

it('builds simple class definition', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function test(string $a)
    {
        return count($a);
    }
}
EOF, $this->index, $this->parser);

    $definition = (new ClassAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator)))
        ->build();

    expect($definition->getMethod('test')->getType()->toString())->toBe('<TA is string>(TA): (λcount)(TA)');
});

it('builds auto resolving class definition', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function test(string $a)
    {
        return count($a);
    }
}
EOF, $this->index, $this->parser);

    $definition = (new ClassAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator)))->build();

    $autoResolvingClassDefinition = new ClassAutoResolvingDefinition($definition, $this->index);

    expect($autoResolvingClassDefinition->getMethod('test')->getType()->toString())
        ->toBe('(string): int')
        ->and($autoResolvingClassDefinition->getMethod('test')->getIncompleteType()->toString())
        ->toBe('<TA is string>(TA): (λcount)(TA)');
});

it('builds class definition with constructor', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function __construct(string $a)
    {
        $this->wow = $a;
    }
}
EOF, $this->index, $this->parser);

    $definition = (new ClassAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator)))->build();

    expect($definition->getMethod('__construct')->getType()->toString())
        ->toBe('<TA is string>(TWow): void');
});

it('builds class definition with constructor defining template defaults', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function __construct($a)
    {
        $this->wow = count($a);
    }
}
EOF, $this->index, $this->parser);

    $definition = (new ClassAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator)))->build();

    expect($definition->getMethod('__construct')->getType()->toString())
        ->toBe('<TA>(TA): void')
        ->and($definition->templateTypes[0]->default?->toString())
        ->toBe('(λcount)(TA)');
});

it('automatically resolves constructor defining template defaults', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function __construct($a)
    {
        $this->wow = count($a);
    }
}
EOF, $this->index, $this->parser);

    $definition = (new ClassAstDefinitionBuilder('Foo', new AstLocator($this->parser, $reflection->sourceLocator)))->build();
    $autoResolvingDefinition = new ClassAutoResolvingDefinition($definition, $this->index);

    expect($autoResolvingDefinition->getMethod('__construct')->getType()->toString())
        ->toBe('(mixed): void')
        ->and($definition->templateTypes[0]->default?->toString())
        ->toBe('int');
});

it('builds the method definition in deferred way', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public function bar()
    {
        return 12;
    }
}
EOF, $this->index, $this->parser);

    $builder = new ClassDeferredDefinitionBuilder(
        'Foo',
        new AstLocator($this->parser, $reflection->sourceLocator),
    );
    $definition = $builder->build();

    expect($definition->getMethod('bar')->getType()->toString())
        ->toBe('(): int(12)');
});

it('builds the constructor definition in deferred way', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function __construct(string $a)
    {
        $this->wow = $a;
    }
}
EOF, $this->index, $this->parser);

    $builder = new ClassDeferredDefinitionBuilder(
        'Foo',
        new AstLocator($this->parser, $reflection->sourceLocator),
    );
    $definition = $builder->build();

    expect($definition->getMethod('__construct')->getType()->toString())
        ->toBe('<TA is string>(TWow): void');
});

it('builds the constructor definition in deferred and autoresolving way', function () {
    $reflection = ReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public string $wow;
    public function __construct(string $a)
    {
        $this->wow = count($a);
    }
}
EOF, $this->index, $this->parser);

    $definition = $reflection->getDefinition();

    expect($definition->getMethod('__construct')->getType()->toString())
        ->toBe('(string): void')
        ->and($definition->getData()->templateTypes[0]->default?->toString())
        ->toBe('int');
});

it('builds the definition of a built in function', function () {
    $reflection = ReflectionFunction::createFromName('count', $this->index, $this->parser);

    $definition = $reflection->getDefinition();

    expect($definition->getType()->toString())->toBe('(Countable|array, int): int');
});

it('builds the shallow ast definition of a function passed from code', function () {
    $reflection = ReflectionFunction::createFromSource('foo', <<<'EOF'
<?php
function foo () {
  return 12;
}
EOF, $this->index, $this->parser);

    $definition = (new FunctionLikeShallowAstDefinitionBuilder(
        'foo',
        new AstLocator($this->parser, $reflection->sourceLocator),
    ))->build();

    expect($definition->getType()->toString())->toBe('(): mixed');
});

it('builds the definition out of reflection function', function () {
    $reflection = ReflectionFunction::createFromSource('foo', <<<'EOF'
<?php
function foo () {
  return count($a);
}
EOF, $this->index, $this->parser);

    $definition = $reflection->getDefinition();
    assert($definition instanceof FunctionLikeAutoResolvingDefinition);

    expect($definition->getIncompleteType()->toString())
        ->toBe('(): (λcount)(unknown)')
        ->and($definition->getType()->toString())
        ->toBe('(): int');
});

<?php

namespace Dedoc\Scramble\Tests;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeDeferredDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeShallowAstDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory())->createForHostVersion();
    $this->index = new LazyIndex(parser: $this->parser);
});

it('builds the definition of a built in function', function () {
    $reflection = ReflectionFunction::createFromName('count', $this->index, $this->parser);

    $definition = $reflection->getDefinition();

    expect($definition->type->toString())->toBe('(Countable|array, int): int');
});

it('builds the shallow ast definition of a function passed from code', function () {
    $reflection = ReflectionFunction::createFromSource('foo', <<<'EOF'
<?php
function foo () {
  return 12;
}
EOF, $this->index, $this->parser);

    $definition = (new FunctionLikeShallowAstDefinitionBuilder(
        $reflection,
        $this->parser,
    ))->build();

    expect($definition->type->toString())->toBe('(): mixed');
});

it('builds the definition out of reflection function', function () {
    $reflection = ReflectionFunction::createFromSource('foo', <<<'EOF'
<?php
function foo () {
  return count($a);
}
EOF, $this->index, $this->parser);

    $definition = $reflection->getDefinition();
    assert($definition instanceof FunctionLikeDeferredDefinition);

    expect($definition->getIncompleteType()->toString())
        ->toBe('(): (λcount)(unknown)')
        ->and($definition->getType()->toString())
        ->toBe('(): int')
        ->and($definition->definition->getType()->toString())
        ->toBe('(): mixed');
});

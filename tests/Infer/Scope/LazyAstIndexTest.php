<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\LazyAstIndex;

class Foo {}

class Bar
{
    public function bar() {}
}

beforeEach(function () {
    $this->index = new LazyAstIndex;
});

test('analyzes class', function () {
    $definition = $this->index->getClass(Foo::class);

    expect($definition->getData()->name)->toBe(Foo::class);
});

test('analyzes class with methods', function () {
    $definition = $this->index->getClass(Bar::class);

    expect($definition->getData()->methods)->toHaveKeys(['bar']);
});

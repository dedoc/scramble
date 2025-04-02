<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;

it('builds reflection based definition upon request', function () {
    $index = new LazyShallowReflectionIndex;

    $definition = $index->getClass(Foo_LazyShallowReflectionIndexTest::class);

    expect($definition->getData()->name)
        ->toBe(Foo_LazyShallowReflectionIndexTest::class)
        ->and(count($definition->getData()->methods))
        ->toBe(0);
});
it('builds method definition upon request', function () {
    $index = new LazyShallowReflectionIndex;

    $methodDefinition = $index->getClass(Foo_LazyShallowReflectionIndexTest::class)->getMethod('foo');

    expect($methodDefinition->type->getReturnType()->toString())
        ->toBe('int');
});
class Foo_LazyShallowReflectionIndexTest
{
    public int $foo = 42;

    public function foo(): int {}
}

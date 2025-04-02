<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;

beforeEach(function () {
    $this->index = new LazyShallowReflectionIndex;
});

it('handles self return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve(new MethodCallReferenceType(
        new ObjectType(Foo_ShallowTypeResolverTest::class),
        'returnsSelf',
        []
    ));
    expect($type->toString())->toBe(Bar_ShallowTypeResolverTest::class);
});

it('handles static return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve(new MethodCallReferenceType(
        new ObjectType(Foo_ShallowTypeResolverTest::class),
        'returnsStatic',
        []
    ));
    expect($type->toString())->toBe(Foo_ShallowTypeResolverTest::class);
});

it('handles parent return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve(new MethodCallReferenceType(
        new ObjectType(Foo_ShallowTypeResolverTest::class),
        'returnsParent',
        []
    ));
    expect($type->toString())->toBe(Bar_ShallowTypeResolverTest::class);
});

class Foo_ShallowTypeResolverTest extends Bar_ShallowTypeResolverTest
{
    public function returnsParent(): parent {}
}
class Bar_ShallowTypeResolverTest
{
    public function returnsSelf(): self {}

    public function returnsStatic(): static {}
}

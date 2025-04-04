<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;

beforeEach(function () {
    $this->index = new LazyShallowReflectionIndex;
    $this->scope = new GlobalScope;
});

it('handles callable call reference type', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve($this->scope, new CallableCallReferenceType(
        new CallableStringType(__NAMESPACE__.'\\fn_ShallowTypeResolverTest'),
        []
    ));
    expect($type->toString())->toBe('int');
});

it('handles new reference type', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve($this->scope, new NewCallReferenceType(
        Foo_ShallowTypeResolverTest::class,
        []
    ));
    expect($type->toString())->toBe(Foo_ShallowTypeResolverTest::class);
});

it('handles self return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve($this->scope, new MethodCallReferenceType(
        new ObjectType(Foo_ShallowTypeResolverTest::class),
        'returnsSelf',
        []
    ));
    expect($type->toString())->toBe(Bar_ShallowTypeResolverTest::class);
});

it('handles static return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve($this->scope, new MethodCallReferenceType(
        new ObjectType(Foo_ShallowTypeResolverTest::class),
        'returnsStatic',
        []
    ));
    expect($type->toString())->toBe(Foo_ShallowTypeResolverTest::class);
});

it('handles parent return annotation', function () {
    $type = (new ShallowTypeResolver($this->index))->resolve($this->scope, new MethodCallReferenceType(
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
function fn_ShallowTypeResolverTest(): int {}

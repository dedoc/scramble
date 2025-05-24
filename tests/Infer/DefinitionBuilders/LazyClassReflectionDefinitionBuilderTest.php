<?php

namespace Dedoc\Scramble\Tests\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\DefinitionBuilders\LazyClassReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->index = new LazyShallowReflectionIndex;
});

/**
 * @template TValue
 */
class Foo_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return TValue
     */
    public function get() {}
}

/**
 * @extends Foo_LazyClassReflectionDefinitionBuilderTest<int>
 */
class Bar_LazyClassReflectionDefinitionBuilderTest extends Foo_LazyClassReflectionDefinitionBuilderTest {}

test('builds class definition', function () {
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(Bar_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('get')->type->toString())->toBe('(): int');
});

class FooMixin_LazyClassReflectionDefinitionBuilderTest
{
    public function get(): int {}
}

/**
 * @mixin FooMixin_LazyClassReflectionDefinitionBuilderTest
 */
class BarMixin_LazyClassReflectionDefinitionBuilderTest {}

test('builds class definition with mixins', function () {
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(BarMixin_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('get')->type->toString())->toBe('(): int');
});

/**
 * @template T
 */
class FooTemplateMixin_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T
     */
    public function get(): mixed {}
}

/**
 * @template TBar
 *
 * @mixin FooTemplateMixin_LazyClassReflectionDefinitionBuilderTest<TBar>
 */
class BarTemplatedMixin_LazyClassReflectionDefinitionBuilderTest {}

test('builds class definition with template mixins', function () {
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(BarTemplatedMixin_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('get')->type->toString())->toBe('(): TBar');
});
/**
 * @template T
 */
trait FooTemplateTrait_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T
     */
    public function get(): mixed {}
}

/**
 * @template TBar
 */
class BarTemplatedTrait_LazyClassReflectionDefinitionBuilderTest
{
    /** @use FooTemplateTrait_LazyClassReflectionDefinitionBuilderTest<TBar> */
    use FooTemplateTrait_LazyClassReflectionDefinitionBuilderTest;
}

test('builds class definition with use annotation', function () {
    dd(
        (new \ReflectionClass(BarTemplatedTrait_LazyClassReflectionDefinitionBuilderTest::class))
            ->get()
    );
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(BarTemplatedTrait_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('get')->type->toString())->toBe('(): TBar');
});

test('builds vendor definition', function () {
    Benchmark::dd(
        fn () => (new LazyClassReflectionDefinitionBuilder(
            $this->index,
            new \ReflectionClass(Collection::class),
        ))->build(),
        100,
    );

    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(Collection::class),
    ))->build();

    expect($definition->getMethod('get')->type->getReturnType()->toString())->toBe('int');
});

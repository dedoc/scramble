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
 * @template T1
 */
class Foo1TemplateMixin_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T1
     */
    public function getFoo1(): mixed {}
}
/**
 * @template T2
 * @mixin Foo1TemplateMixin_LazyClassReflectionDefinitionBuilderTest<T2>
 */
class Foo2TemplateMixin_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T2
     */
    public function getFoo2(): mixed {}
}
/**
 * @template T3
 * @mixin Foo2TemplateMixin_LazyClassReflectionDefinitionBuilderTest<T3>
 */
class Foo3TemplateMixin_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T3
     */
    public function getFoo3(): mixed {}
}
test('builds class definition with multilevel template mixins', function () {
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(Foo3TemplateMixin_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('getFoo1')->type->toString())->toBe('(): T3');
});

/**
 * @template T
 *
 * @mixin CircularBarTemplatedMixin_LazyClassReflectionDefinitionBuilderTest
 */
class CircularFooTemplateMixin_LazyClassReflectionDefinitionBuilderTest
{
    /**
     * @return T
     */
    public function get(): mixed {}
}
/**
 * @template TBar
 *
 * @mixin CircularFooTemplateMixin_LazyClassReflectionDefinitionBuilderTest<TBar>
 */
class CircularBarTemplatedMixin_LazyClassReflectionDefinitionBuilderTest {}
test('builds class definition with template circular mixins', function () {
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(CircularBarTemplatedMixin_LazyClassReflectionDefinitionBuilderTest::class),
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
    $definition = (new LazyClassReflectionDefinitionBuilder(
        $this->index,
        new \ReflectionClass(BarTemplatedTrait_LazyClassReflectionDefinitionBuilderTest::class),
    ))->build();

    expect($definition->getMethod('get')->type->toString())->toBe('(): TBar');
});

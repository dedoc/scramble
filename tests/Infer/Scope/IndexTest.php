<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;

it('doesnt fail on internal class definition request', function () {
    $index = new Index;

    $def = $index->getClass(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});

class Bar_IndexTest
{
    public function foo(): int {}
}

beforeEach(function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            Bar_IndexTest::class,
            BarGeneric_IndexTest::class,
        ]);

    $this->index = new Index;
});

class Foo_IndexTest extends Bar_IndexTest {}
it('can get primitive type from non-ast analyzable class', function () {
    $type = $this->index->getClass(Foo_IndexTest::class)->getMethod('foo')->getReturnType();

    expect($type->toString())->toBe('int');
});

/** @template T */
class BarGeneric_IndexTest
{
    /** @return T */
    public function foo() {}
}
it('can get generic type from non-ast analyzable class', function () {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new MethodCallReferenceType(
                new Generic(BarGeneric_IndexTest::class, [new StringType]),
                'foo',
                [],
            ),
        );

    expect($type->toString())->toBe('string');
});

/** @extends BarGeneric_IndexTest<string> */
class FooGeneric_IndexTest extends BarGeneric_IndexTest {}
it('can get generic type from extended non-ast analyzable class', function () {
    $type = $this->index->getClass(FooGeneric_IndexTest::class)
        ->getMethod('foo')
        ->type->getReturnType();

    expect($type->toString())->toBe('string');
});

/**
 * @mixin Bar_IndexTest
 */
class FooMixin_IndexTest {}
test('builds class definition with mixin without trait', function () {
    $type = $this->index->getClass(FooMixin_IndexTest::class)
        ->getMethod('foo')
        ->type->getReturnType();

    expect($type->toString())->toBe('int');
});

trait BarT_IndexTest
{
    public function foo(): bool {}
}
class FooTrait_IndexTest
{
    use BarT_IndexTest;
}
test('builds class definition with mixin with trait', function () {
    $type = $this->index->getClass(FooTrait_IndexTest::class)
        ->getMethod('foo')
        ->type->getReturnType();

    expect($type->toString())->toBe('boolean');
});

/** @template T */
trait BarTGeneric_IndexTest
{
    /** @return T */
    public function foo() {}
}
class FooTraitGeneric_IndexTest
{
    /** @uses BarTGeneric_IndexTest<42> */
    use BarTGeneric_IndexTest;
}
test('builds class definition with mixin with generic trait', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            BarTGeneric_IndexTest::class,
        ]);

    $type = $this->index->getClass(FooTraitGeneric_IndexTest::class)
        ->getMethod('foo')
        ->type->getReturnType();

    expect($type->toString())->toBe('int(42)');
});

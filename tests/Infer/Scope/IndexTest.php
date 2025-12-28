<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->index = new Index;
});

it('doesnt fail on internal class definition request', function () {
    $def = $this->index->getClass(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});

it('retrieves function definitions', function () {
    $def = $this->index->getFunction('is_null');

    expect($def)->toBeInstanceOf(FunctionLikeDefinition::class);
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

class Template_IndexTest
{
    /**
     * @template T
     *
     * @param  T  $a
     * @return T
     */
    public function foo($a) {}
}
it('can get template type from non-ast analyzable class', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            Template_IndexTest::class,
        ]);

    $type = $this->index->getClass(Template_IndexTest::class)->getMethod('foo')->type->toString();

    expect($type)->toBe('<T>(T): T');
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
        ->getReturnType();

    expect($type->toString())->toBe('string');
});

/**
 * @mixin Bar_IndexTest
 */
class FooMixin_IndexTest {}
test('builds class definition with mixin without trait', function () {
    $type = $this->index->getClass(FooMixin_IndexTest::class)
        ->getMethod('foo')
        ->getReturnType();

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
        ->getReturnType();

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
    /** @use BarTGeneric_IndexTest<42> */
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
        ->getReturnType();

    expect($type->toString())->toBe('int(42)');
});

/**
 * @template T
 *
 * @extends TParent_IndexTest<T>
 */
class T_IndexTest extends TParent_IndexTest {}
/** @template T */
class TParent_IndexTest {}
it('properly stores templates in definitions', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            T_IndexTest::class,
            TParent_IndexTest::class,
        ]);

    $definition = $this->index->getClass(T_IndexTest::class);

    expect($definition->templateTypes)->toHaveCount(1)
        ->and($definition->templateTypes[0]->name)->toBe('T');
});

/** @mixin BazTrait_IndexTest<int> */
class BazParent_IndexTest {}
/** @template T */
trait BazTrait_IndexTest
{
    /** @return T */
    public function foo() {}
}
class Baz_IndexTest extends BazParent_IndexTest {}
it('handles deep context with mixin', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            BazParent_IndexTest::class,
            BazTrait_IndexTest::class,
            Baz_IndexTest::class,
        ]);

    $definition = $this->index->getClass(Baz_IndexTest::class);

    expect($definition->getMethod('foo')->getReturnType()->toString())->toBe('int');
});

/** @mixin BazClass_IndexTest<int> */
class BazUsesClass_IndexTest {}
/** @template T */
class BazClass_IndexTest
{
    /** @return T */
    public function foo() {}
}
it('handles deep context with mixed in class', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            BazUsesClass_IndexTest::class,
            BazClass_IndexTest::class,
        ]);

    $definition = $this->index->getClass(BazUsesClass_IndexTest::class);

    expect($definition->getMethod('foo')->getReturnType()->toString())->toBe('int');
});

/** @template T */
class BazUseParent_IndexTest
{
    /** @use BazTrait_IndexTest<T> */
    use BazTrait_IndexTest;
}
/** @extends BazUseParent_IndexTest<int> */
class BazUse_IndexTest extends BazUseParent_IndexTest {}
it('handles deep context with use', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            BazUseParent_IndexTest::class,
            BazTrait_IndexTest::class,
            BazUse_IndexTest::class,
        ]);

    $definition = $this->index->getClass(BazUse_IndexTest::class);

    expect($definition->getMethod('foo')->getReturnType()->toString())->toBe('int');
});

it('infers complex type from flatMap', function () {
    $collectionType = new Generic(Collection::class, [
        new IntegerType,
        new StringType,
    ]);
    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType($collectionType, 'flatMap', [
            new FunctionType('{}', [], new Generic(Collection::class, [
                new IntegerType,
                new IntegerType,
            ])),
        ]),
    );

    expect($type->toString())->toBe(Collection::class.'<int, int>');
});

it('handles collection get call', function () {
    $type = getStatementType('(new '.Collection::class.'())->get(1, fn () => 1)');

    expect($type->toString())->toBe('unknown|int(1)');
});

it('handles collection first call', function () {
    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType(new Generic(Collection::class, [new IntegerType, new IntegerType]), 'first', [new FunctionType('{}', [], new IntegerType)]),
    );

    expect($type->toString())->toBe('int|null');
});

it('handles collection map call', function () {
    $type = getStatementType('(new '.Collection::class.'())->map(fn () => 1)');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int|string, int(1)>');
});

it('handles collection empty construct call', function () {
    $type = getStatementType('(new '.Collection::class.'([]))');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int|string, unknown>');
});

it('handles collection construct call', function () {
    $type = getStatementType('(new '.Collection::class.'([42]))');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int, int(42)>');
});

it('handles collection map call with undefined type', function () {
    $type = getStatementType('(new '.Collection::class.'([["a" => 42]]))->map(fn ($v) => $v["a"])');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int, int(42)>');
});

it('handles collection map call with primitive type', function () {
    $type = getStatementType('(new '.Collection::class.'([["a" => 42]]))->map(fn (int $v) => $v)');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int, int>');
});

it('handles collection keys call', function () {
    $type = getStatementType('(new '.Collection::class.'(["foo" => "bar"]))');

    expect($type->toString())->toBe('Illuminate\Support\Collection<string(foo), string(bar)>');
});

it('handles class definition logic when class is alias and mixin', function () {
    Scramble::infer()->configure()->buildDefinitionsUsingReflectionFor([
        'TheAliasForAliased_IndexTest',
        Aliased_IndexTest::class,
    ]);

    $def = $this->index
        ->getClass(Aliased_IndexTest::class)
        ->getMethod('count');

    expect($def)->not->toBeNull();
});
/**
 * @mixin \TheAliasForAliased_IndexTest
 */
class Aliased_IndexTest
{
    public function count() {}
}
class_alias(Aliased_IndexTest::class, 'TheAliasForAliased_IndexTest');

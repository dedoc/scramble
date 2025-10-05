<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

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

class UserModel_IndexTest extends Model
{
    public function posts()
    {
        return $this->hasMany(PostModel_IndexTest::class);
    }

    public function foo()
    {
        return 42;
    }
}

class PostModel_IndexTest extends Model {}

it('handles static', function () {
    $type = getStatementType(UserModel_IndexTest::class.'::query()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_IndexTest::class.'>');
});

it('handles static method call', function () {
    $type = getStatementType(UserModel_IndexTest::class.'::query()->applyScopes()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_IndexTest::class.'>');
});

it('handles chained method call', function () {
    $type = getStatementType(UserModel_IndexTest::class.'::query()->where()->firstOrFail()');

    expect($type->toString())->toBe(UserModel_IndexTest::class);
});

it('handles chained method call relation', function () {
    $type = getStatementType('(new '.UserModel_IndexTest::class.')->posts()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Relations\HasMany<'.PostModel_IndexTest::class.', self>');
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

it('handles mixin data', function () {
    $def = $this->index->getClass(Relation::class);

    $firstMethod = $def->getMethod('first');

    expect($firstMethod->getReturnType()->toString())->toBe('TRelatedModel|null');
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
            ]))
        ]),
    );

    expect($type->toString())->toBe(Collection::class.'<int, int>');
});

it('handles chained method call relation first', function () {
    $hasMany = new Generic(HasMany::class, [
        new ObjectType(PostModel_IndexTest::class),
        new SelfType(''),
    ]);
    $type = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new MethodCallReferenceType($hasMany, 'first', []),
    );

    expect($type->toString())->toBe(PostModel_IndexTest::class.'|null');
});

it('handles updateOrCreate model call ', function () {
    $type = getStatementType(PostModel_IndexTest::class.'::updateOrCreate()');

    expect($type->toString())->toBe(PostModel_IndexTest::class);
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

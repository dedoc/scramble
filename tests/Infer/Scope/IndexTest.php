<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

it('doesnt fail on internal class definition request', function () {
    $index = new Index;

    $def = $index->getClass(\Error::class);

    expect($def)->toBeInstanceOf(ClassDefinition::class);
});

it('builds definition', function () {
    $index = new Index(new LazyShallowReflectionIndex);

    $definition = $index->getClass(UserModel_IndexTest::class);

    expect($definition->getData()->name)
        ->toBe(UserModel_IndexTest::class)
        ->and($definition->getData()->methods['foo']->isFullyAnalyzed)
        ->toBeFalse()
        ->and($definition->getData()->methods['fill']->isFullyAnalyzed)
        ->toBeFalse()
        ->and($definition->getMethod('update')->type->toString())
        ->toBe('(array<mixed>, array<mixed>): boolean');
});

it('returns vendor class definition when requested', function () {
    $index = new Index(new LazyShallowReflectionIndex);

    $definition = $index->getClass(Collection::class);

    expect($definition->getData()->name)
        ->toBe(Collection::class)
        ->and($definition->getMethod('first'))
        ->not->toBeNull();
});

it('infers query method of the model', function () {
    $type = getStatementType(UserModel_IndexTest::class.'::query()->paginate()');

    /*
     * In Laravel 12 the Illuminate\Pagination\LengthAwarePaginator type will be more concrete, thanks to proper
     * type annotations.
     */
    expect($type->toString())->toStartWith('Illuminate\Pagination\LengthAwarePaginator<int, ');
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

it('handles collection map call', function () {
    $type = getStatementType('(new '.Collection::class.'())->map(fn () => 1)');

    expect($type->toString())->toBe('Illuminate\Support\Collection<unknown, int(1)>');
});

it('handles collection map deep call', function () {
    $type = getStatementType('(new '.Collection::class.'())->map(fn () => ["a" => "foo"])');

    expect($type->toString())->toBe('Illuminate\Support\Collection<unknown, int(1)>');
});

it('handles collection construct call', function () {
    $type = getStatementType('(new '.Collection::class.'([42]))');

    expect($type->toString())->toBe('Illuminate\Support\Collection<unknown, int(1)>');
});

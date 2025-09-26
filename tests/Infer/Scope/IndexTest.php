<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;

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
        ->toBe('(array, array): boolean');
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
    $type = getStatementType(UserModel_IndexTest::class . '::query()->paginate()');

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

class PostModel_IndexTest extends Model
{
}

it('handles static', function () {
    $type = getStatementType(UserModel_IndexTest::class . '::query()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<' . UserModel_IndexTest::class . '>');
});

it('handles static method call', function () {
    $type = getStatementType(UserModel_IndexTest::class . '::query()->applyScopes()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<' . UserModel_IndexTest::class . '>');
});

it('handles chained method call', function () {
    $type = getStatementType(UserModel_IndexTest::class . '::query()->where()->firstOrFail()');

    expect($type->toString())->toBe(UserModel_IndexTest::class);
});

it('handles chained method call rel', function () {
    $type = getStatementType('(new '.UserModel_IndexTest::class . ')->posts()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Relations\HasMany<'.PostModel_IndexTest::class.', self>');
});

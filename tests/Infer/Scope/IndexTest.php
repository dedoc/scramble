<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
//            dd(app(Index::class)->getClass(\Illuminate\Database\Eloquent\Builder::class)->getMethod('firstOrNew'));
    //
    $type = getStatementType(UserModel_IndexTest::class.'::query()->first([], [])');

    //    $type = getStatementType(UserModel_IndexTest::class.'::query()');

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_IndexTest::class.'>');
});

class UserModel_IndexTest extends Model
{
    public function foo()
    {
        return 42;
    }
}

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

    //    dd($type->toString());

    expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_IndexTest::class.'>');
});

<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlainModel_EloquentBuilderExtensionTest extends Model {}

test('supports extension methods', function (string $method, string $expectedType) {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new MethodCallReferenceType(
                new Generic(Builder::class, [new ObjectType(SamplePostModel::class)]),
                $method,
                [],
            )
        );

    expect($type->toString())->toBe($expectedType);
})->with([
    // forwards call to scope
    ['approved', Builder::class.'<'.SamplePostModel::class.'>'],
    ['approvedTypedParam', Builder::class.'<'.SamplePostModel::class.'>'],

    // supports soft deletes macro methods
    ['onlyTrashed', Builder::class.'<'.SamplePostModel::class.'>'],
    ['withTrashed', Builder::class.'<'.SamplePostModel::class.'>'],
    ['withoutTrashed', Builder::class.'<'.SamplePostModel::class.'>'],
    ['restoreOrCreate', SamplePostModel::class],
    ['createOrRestore', SamplePostModel::class],
]);

it('tracks relations passed to with() on builder', function (string $expression, string $expectedRelationsType) {
    $builderType = getStatementType($expression);

    expect($builderType)->toBeInstanceOf(Generic::class)->and($builderType->name)->toBe(Builder::class);

    $relationsType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($builderType->templateTypes[0], 'relations'),
        );

    expect($relationsType->toString())->toBe($expectedRelationsType);
})->with([
    'variadic strings' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user', 'comments')->with('team')",
        'list{string(user), string(comments), string(team)}',
    ],
    'relation with closure' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('posts', fn (\$q) => \$q)",
        'list{string(posts)}',
    ],
    'array relations' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with(['posts' => fn (\$q) => \$q, 'comments' => fn (\$q) => \$q])",
        'list{string(posts), string(comments)}',
    ],
    'mixed array relations' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with(['users', 'posts' => fn (\$q) => \$q, 'comments' => fn (\$q) => \$q])",
        'list{string(users), string(posts), string(comments)}',
    ],
    'without variadic strings' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user', 'comments', 'team')->without('user', 'comments')",
        'list{string(team)}',
    ],
    'without array relations' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with(['user', 'comments', 'team'])->without(['user'])",
        'list{string(comments), string(team)}',
    ],
    'withOnly replaces eager loads' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user', 'comments')->withOnly('team')",
        'list{string(team)}',
    ],
    'withOnly array relations' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user')->withOnly(['posts', 'comments'])",
        'list{string(posts), string(comments)}',
    ],
    'setEagerLoads replaces eager loads' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user')->setEagerLoads(['posts', 'comments'])",
        'list{string(posts), string(comments)}',
    ],
    'withoutEagerLoads clears eager loads' => [
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user', 'comments')->withoutEagerLoads()",
        'list{}',
    ],
]);

it('carries loaded relations from builder', function (string $expression, string $expectedType, string $expectedRelationsType) {
    $type = getStatementType(Str::replace(
        '$builder',
        PlainModel_EloquentBuilderExtensionTest::class."::query()->with('user', 'comments', 'team')",
        $expression,
    ));
    expect($type->toString())->toBe($expectedType);

    $modelType = (new TypeWalker)->first(
        $type,
        fn ($t) => $t->isInstanceOf(PlainModel_EloquentBuilderExtensionTest::class),
    );
    expect($modelType)->not->toBeNull();

    $relationsType = ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new PropertyFetchReferenceType($modelType, 'relations'),
    );

    expect($relationsType->toString())->toBe($expectedRelationsType);
})->with([
    'first' => [
        '$builder->first()',
        PlainModel_EloquentBuilderExtensionTest::class.'|null',
        'list{string(user), string(comments), string(team)}',
    ],
    'get' => [
        '$builder->get()',
        'Illuminate\Database\Eloquent\Collection<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'find' => [
        '$builder->find(1)',
        PlainModel_EloquentBuilderExtensionTest::class.'|null',
        'list{string(user), string(comments), string(team)}',
    ],
    'findSole' => [
        '$builder->findSole(1)',
        PlainModel_EloquentBuilderExtensionTest::class,
        'list{string(user), string(comments), string(team)}',
    ],
    'sole' => [
        '$builder->sole()',
        PlainModel_EloquentBuilderExtensionTest::class,
        'list{string(user), string(comments), string(team)}',
    ],
    'findOrFail' => [
        '$builder->findOrFail(1)',
        PlainModel_EloquentBuilderExtensionTest::class,
        'list{string(user), string(comments), string(team)}',
    ],
    'firstOrFail' => [
        '$builder->firstOrFail()',
        PlainModel_EloquentBuilderExtensionTest::class,
        'list{string(user), string(comments), string(team)}',
    ],
    'findMany' => [
        '$builder->findMany([1, 2])',
        'Illuminate\Database\Eloquent\Collection<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'paginate' => [
        '$builder->paginate()',
        'Illuminate\Pagination\LengthAwarePaginator<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'fastPaginate' => [
        '$builder->fastPaginate()',
        'Illuminate\Pagination\LengthAwarePaginator<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'simplePaginate' => [
        '$builder->simplePaginate()',
        'Illuminate\Pagination\Paginator<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'simpleFastPaginate' => [
        '$builder->simpleFastPaginate()',
        'Illuminate\Pagination\Paginator<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
    'cursorPaginate' => [
        '$builder->cursorPaginate()',
        'Illuminate\Pagination\CursorPaginator<int, '.PlainModel_EloquentBuilderExtensionTest::class.'>',
        'list{string(user), string(comments), string(team)}',
    ],
]);

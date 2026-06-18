<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;

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

it('tracks relations passed to with() on builder', function (string $expression, string $expectedEagerLoadType) {
    $builderType = getStatementType($expression);

    expect($builderType)->toBeInstanceOf(Generic::class)
        ->and($builderType->name)->toBe(Builder::class);

    $eagerLoadType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($builderType, 'eagerLoad'),
        );

    expect($eagerLoadType->toString())->toBe($expectedEagerLoadType);
})->with([
    'variadic strings' => [
        SamplePostModel::class."::query()->with('user', 'comments')->with('team')",
        'list{string(user), string(comments), string(team)}',
    ],
    'relation with closure' => [
        SamplePostModel::class."::query()->with('posts', fn (\$q) => \$q)",
        'list{string(posts)}',
    ],
    'array relations' => [
        SamplePostModel::class."::query()->with(['posts' => fn (\$q) => \$q, 'comments' => fn (\$q) => \$q])",
        'list{string(posts), string(comments)}',
    ],
    'mixed array relations' => [
        SamplePostModel::class."::query()->with(['users', 'posts' => fn (\$q) => \$q, 'comments' => fn (\$q) => \$q])",
        'list{string(users), string(posts), string(comments)}',
    ],
]);

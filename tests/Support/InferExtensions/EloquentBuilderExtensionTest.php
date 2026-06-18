<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\WithProperties;
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


/**
 * @return WithProperties<$this, array{eagerLoad: ArrayMerge<$this->eagerLoad, $callback is Closure ? list{$relations} : $relations is array ? array-keys<$relations> : null>}>
 */
it('tracks relations passed to with() on builder', function () {
    $builderType = getStatementType(SamplePostModel::class."::query()->with('user', 'comments')->with('team')");

    expect($builderType)->toBeInstanceOf(Generic::class)
        ->and($builderType->name)->toBe(Builder::class);

    $eagerLoadType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($builderType, 'eagerLoad'),
        );

    expect($eagerLoadType->toString())->toBe('list{string(user), string(comments), string(team)}');
});

it('dssdsd', function () {
    $phpDocType = 'WithProperties<$this, array{eagerLoad: ArrayMerge<PropertyFetch<$this, \'eagerLoad\'>, ($callback is Closure ? list{TRelations} : ($relations is string ? Arguments : key-of<TRelations>))>}>';

    $type = PhpDoc::parse('/** @return '.$phpDocType.' */');

    dd($type);
});

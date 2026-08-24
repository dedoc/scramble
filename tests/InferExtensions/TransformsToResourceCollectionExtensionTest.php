<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Attributes\UseResourceCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->infer = app(Infer::class);
});

$skip = fn () => ! method_exists(\Illuminate\Support\Collection::class, 'toResourceCollection');

it('toResourceCollection returns AnonymousResourceCollection for explicitly passed resource class', function () {
    $collectionType = new Generic(EloquentCollection::class, [
        new IntegerType,
        new ObjectType(SampleUserModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                $collectionType,
                'toResourceCollection',
                [new LiteralStringType(CollectionExtensionTest_Resource::class)]
            )
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.EloquentCollection::class.'<int, '.SampleUserModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

class CollectionExtensionTest_Resource extends \Illuminate\Http\Resources\Json\JsonResource {}

it('toResourceCollection resolves dedicated collection from UseResourceCollection attribute', function () {
    $collectionType = new Generic(EloquentCollection::class, [
        new IntegerType,
        new ObjectType(CollectionExtensionTest_UseResourceCollectionModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($collectionType, 'toResourceCollection', [])
        );

    expect($type->toString())->toBe(CollectionExtensionTest_DedicatedCollection::class.'<'.CollectionExtensionTest_UseResourceCollectionModel::class.'>');
})->skip($skip);

#[UseResourceCollection(CollectionExtensionTest_DedicatedCollection::class)]
class CollectionExtensionTest_UseResourceCollectionModel extends SampleUserModel {}

class CollectionExtensionTest_DedicatedCollection extends \Illuminate\Http\Resources\Json\ResourceCollection {}

it('toResourceCollection resolves AnonymousResourceCollection from UseResource attribute', function () {
    $collectionType = new Generic(EloquentCollection::class, [
        new IntegerType,
        new ObjectType(CollectionExtensionTest_UseResourceModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($collectionType, 'toResourceCollection', [])
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.EloquentCollection::class.'<int, '.CollectionExtensionTest_UseResourceModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

#[UseResource(CollectionExtensionTest_Resource::class)]
class CollectionExtensionTest_UseResourceModel extends SampleUserModel {}

it('toResourceCollection resolves dedicated collection from guessed class name', function () {
    $collectionType = new Generic(EloquentCollection::class, [
        new IntegerType,
        new ObjectType(CollectionExtensionTest_GuessingWithCollectionModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($collectionType, 'toResourceCollection', [])
        );

    expect($type->toString())->toBe(CollectionExtensionTest_GuessingResourceCollection::class.'<'.CollectionExtensionTest_GuessingWithCollectionModel::class.'>');
})->skip($skip);

class CollectionExtensionTest_GuessingWithCollectionModel extends SampleUserModel
{
    public static function guessResourceName(): array
    {
        return [CollectionExtensionTest_GuessingResource::class];
    }
}

class CollectionExtensionTest_GuessingResource extends \Illuminate\Http\Resources\Json\JsonResource {}

class CollectionExtensionTest_GuessingResourceCollection extends \Illuminate\Http\Resources\Json\ResourceCollection {}

it('toResourceCollection falls back to AnonymousResourceCollection when no dedicated collection class exists', function () {
    $collectionType = new Generic(EloquentCollection::class, [
        new IntegerType,
        new ObjectType(CollectionExtensionTest_GuessingWithResourceOnlyModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($collectionType, 'toResourceCollection', [])
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.EloquentCollection::class.'<int, '.CollectionExtensionTest_GuessingWithResourceOnlyModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

class CollectionExtensionTest_GuessingWithResourceOnlyModel extends SampleUserModel
{
    public static function guessResourceName(): array
    {
        return [CollectionExtensionTest_Resource::class];
    }
}

it('toResourceCollection returns AnonymousResourceCollection when called on paginate result', function () {
    $paginatorType = new Generic(LengthAwarePaginator::class, [
        new IntegerType,
        new ObjectType(SampleUserModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                $paginatorType,
                'toResourceCollection',
                [new LiteralStringType(CollectionExtensionTest_Resource::class)]
            )
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

it('toResourceCollection returns AnonymousResourceCollection when called on cursorPaginate result', function () {
    $paginatorType = new Generic(CursorPaginator::class, [
        new IntegerType,
        new ObjectType(SampleUserModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                $paginatorType,
                'toResourceCollection',
                [new LiteralStringType(CollectionExtensionTest_Resource::class)]
            )
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.CursorPaginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

it('toResourceCollection returns AnonymousResourceCollection when called on simplePaginate result', function () {
    $paginatorType = new Generic(Paginator::class, [
        new IntegerType,
        new ObjectType(SampleUserModel::class),
    ]);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                $paginatorType,
                'toResourceCollection',
                [new LiteralStringType(CollectionExtensionTest_Resource::class)]
            )
        );

    expect($type->toString())->toBe(
        AnonymousResourceCollection::class.'<'.Paginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, '.CollectionExtensionTest_Resource::class.'>'
    );
})->skip($skip);

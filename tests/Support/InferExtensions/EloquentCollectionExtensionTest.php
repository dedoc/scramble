<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

describe('eloquent collection load/loadMissing', function () {
    it('tracks relations passed to load() on collection', function (string $expression, string $expectedRelationsType) {
        $type = getStatementType($expression);

        $modelType = (new TypeWalker)->first(
            $type,
            fn ($t) => $t->isInstanceOf(SamplePostModel::class),
        );

        expect($modelType)->not->toBeNull();

        $relationsType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new PropertyFetchReferenceType($modelType, 'relations'),
            );

        expect($relationsType->toString())->toBe($expectedRelationsType);
    })->with([
        'variadic strings' => [
            SamplePostModel::class."::all()->load('comments', 'team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'array relations' => [
            SamplePostModel::class."::all()->load(['comments', 'team'])",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'fresh collection' => [
            '(new '.EloquentCollection::class.'([new '.SamplePostModel::class.']))->load(\'comments\')',
            'list{string(comments)}',
        ],
    ]);

    it('tracks relations passed to loadMissing() on collection', function (string $expression, string $expectedRelationsType) {
        $type = getStatementType($expression);

        $modelType = (new TypeWalker)->first(
            $type,
            fn ($t) => $t->isInstanceOf(SamplePostModel::class),
        );

        expect($modelType)->not->toBeNull();

        $relationsType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new PropertyFetchReferenceType($modelType, 'relations'),
            );

        expect($relationsType->toString())->toBe($expectedRelationsType);
    })->with([
        'variadic strings' => [
            SamplePostModel::class."::all()->loadMissing('comments', 'team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'array relations' => [
            SamplePostModel::class."::all()->loadMissing(['comments', 'team'])",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'after load' => [
            SamplePostModel::class."::all()->load('comments')->loadMissing('team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
    ]);
})->skip(fn () => ! version_compare(app()->version(), '11.15.0', '>='));

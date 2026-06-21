<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function () {
    $this->index = new Index;
});

class UserModel_ModelExtensionTest extends Model
{
    public function posts()
    {
        return $this->hasMany(PostModel_ModelExtensionTest::class);
    }

    public function foo()
    {
        return 42;
    }
}

class PostModel_ModelExtensionTest extends Model {}

describe('model annotations (introduced in 11.15.0)', function () {
    it('handles static', function () {
        $type = getStatementType(UserModel_ModelExtensionTest::class.'::query()');

        expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_ModelExtensionTest::class.'>');
    });

    it('handles static method call', function () {
        $type = getStatementType(UserModel_ModelExtensionTest::class.'::query()->applyScopes()');

        expect($type->toString())->toBe('Illuminate\Database\Eloquent\Builder<'.UserModel_ModelExtensionTest::class.'>');
    });

    it('handles chained method call', function () {
        $type = getStatementType(UserModel_ModelExtensionTest::class.'::query()->where()->firstOrFail()');

        expect($type->toString())->toBe(UserModel_ModelExtensionTest::class);
    });

    it('handles chained method call relation', function () {
        $type = getStatementType('(new '.UserModel_ModelExtensionTest::class.')->posts()');

        expect($type->toString())->toBe('Illuminate\Database\Eloquent\Relations\HasMany<'.PostModel_ModelExtensionTest::class.', '.UserModel_ModelExtensionTest::class.'>');
    });

    it('handles mixin data', function () {
        $def = $this->index->getClass(Relation::class);

        $firstMethod = $def->getMethod('first');

        expect($firstMethod->getReturnType()->toString())->toBe('TRelatedModel|null');
    });

    it('handles chained method call relation first', function () {
        $hasMany = new Generic(HasMany::class, [
            new ObjectType(PostModel_ModelExtensionTest::class),
            new SelfType(''),
        ]);
        $type = ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new MethodCallReferenceType($hasMany, 'first', []),
        );

        expect($type->toString())->toBe(PostModel_ModelExtensionTest::class.'|null');
    });

    it('handles updateOrCreate model call ', function () {
        $type = getStatementType(PostModel_ModelExtensionTest::class.'::updateOrCreate()');

        expect($type->toString())->toBe(PostModel_ModelExtensionTest::class);
    });

    it('seeds model relations from $with on query()', function () {
        $builderType = getStatementType(SamplePostModel::class.'::query()');

        expect($builderType)->toBeInstanceOf(Generic::class)
            ->and($builderType->name)->toBe(Builder::class);

        $relationsType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new PropertyFetchReferenceType($builderType->templateTypes[0], 'relations'),
            );

        expect($relationsType->toString())->toBe('list{string(parent), string(children), string(user)}');
    });

    it('carries loaded relations from $with through static model shortcuts', function (string $expression, string $expectedRelationsType) {
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
        'find' => [
            SamplePostModel::class.'::find(1)',
            'list{string(parent), string(children), string(user)}',
        ],
        'where first' => [
            SamplePostModel::class."::where('id', 1)->first()",
            'list{string(parent), string(children), string(user)}',
        ],
        'all' => [
            SamplePostModel::class.'::all()',
            'list{string(parent), string(children), string(user)}',
        ],
    ]);

    it('tracks relations passed to load() on model', function (string $expression, string $expectedRelationsType) {
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
            SamplePostModel::class."::find(1)->load('comments', 'team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'array relations' => [
            SamplePostModel::class."::find(1)->load(['comments', 'team'])",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'fresh model' => [
            '(new '.SamplePostModel::class.")->load('comments')",
            'list{string(comments)}',
        ],
    ]);

    it('tracks relations passed to loadMissing() on model', function (string $expression, string $expectedRelationsType) {
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
            SamplePostModel::class."::find(1)->loadMissing('comments', 'team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'array relations' => [
            SamplePostModel::class."::find(1)->loadMissing(['comments', 'team'])",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
        'after load' => [
            SamplePostModel::class."::find(1)->load('comments')->loadMissing('team')",
            'list{string(parent), string(children), string(user), string(comments), string(team)}',
        ],
    ]);
})->skip(fn () => ! version_compare(app()->version(), '11.15.0', '>='));

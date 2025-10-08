<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
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

        expect($type->toString())->toBe('Illuminate\Database\Eloquent\Relations\HasMany<'.PostModel_ModelExtensionTest::class.', self>');
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
})->skip(fn () => ! version_compare(app()->version(), '11.15.0', '>='));

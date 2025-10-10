<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->infer = app(Infer::class);
});

it('adds models attributes to the model class definition as properties', function () {
    $this->infer->analyzeClass(SamplePostModel::class);

    $object = new ObjectType(SamplePostModel::class);

    $expectedPropertiesTypes = [
        /* Attributes from the DB */
        'id' => 'int',
        'status' => 'Status',
        'user_id' => 'int',
        'title' => 'string',
        'settings' => 'array<mixed>|null',
        'body' => 'string',
        'approved_at' => 'Carbon\Carbon|null',
        'created_at' => 'Carbon\Carbon|null',
        'updated_at' => 'Carbon\Carbon|null',
        /* Appended attributes */
        'read_time' => 'unknown',
        /* Relations */
        'user' => 'SampleUserModel',
        'parent' => 'SamplePostModel',
        'children' => 'Illuminate\Database\Eloquent\Collection<int, SamplePostModel>',
        /* other properties from model class are ommited here but exist on type */
    ];

    foreach ($expectedPropertiesTypes as $name => $type) {
        $propertyType = $object->getPropertyType($name);

        expect(Str::replace('Dedoc\\Scramble\\Tests\\Files\\', '', $propertyType->toString()))
            ->toBe($type);
    }
});

it('adds toArray method type the model class without defined toArray class', function () {
    $this->infer->analyzeClass(SampleUserModel::class);

    $scope = new Infer\Scope\GlobalScope;
    $scope->index = $this->infer->index;

    $toArrayReturnType = (new ObjectType(SampleUserModel::class))
        ->getMethodReturnType('toArray', scope: $scope);

    expect(collect($toArrayReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key.($t->isOptional ? '?' : '') => $t->value->toString()]))
        ->toMatchArray([
            'id' => 'int',
            'name' => 'string',
            'email' => 'string',
            'password' => 'string',
            'remember_token' => 'string|null',
            'created_at' => 'string|null',
            'updated_at' => 'string|null',
        ]);
});

/*
 * `AsEnumCollection::of` is added in Laravel 11, hence this check so tests are passing with Laravel 10.
 */
if (method_exists(AsEnumCollection::class, 'of')) {
    it('casts generic enum collections', function () {
        $this->infer->analyzeClass(SampleUserModel::class);

        $object = new ObjectType(SampleUserModel::class);

        $propertyType = $object->getPropertyType('roles');

        expect(Str::replace('Dedoc\\Scramble\\Tests\\Files\\', '', $propertyType->toString()))
            ->toBe('Illuminate\Support\Collection<int, Role>');
    });
}

/*
 * When resolving property types from models that are in vendor directory,
 * we want to make sure that the extensions broker is called before attempting
 * to analyze the class definition. This is important because vendor files
 * are not directly analyzed, but we can still infer their properties through
 * database schema and other Laravel conventions.
 *
 * This test ensures that PropertyFetchReferenceType resolution works correctly
 * with vendor files by trying the extensions broker first, even when the class
 * is not in our index.
 */
it('can fetch properties from vendor Role model', function () {
    $object = new ObjectType(Role::class);

    $expectedProperties = [
        'id' => 'int',
        'name' => 'string',
        'guard_name' => 'string',
        'created_at' => 'Carbon\Carbon|null',
        'updated_at' => 'Carbon\Carbon|null',
    ];

    foreach ($expectedProperties as $name => $type) {
        $propertyType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new Infer\Scope\GlobalScope,
                new PropertyFetchReferenceType($object, $name)
            );

        expect(Str::replace('Dedoc\\Scramble\\Tests\\Files\\', '', $propertyType->toString()))
            ->toBe($type);
    }
});

it('supports getOriginal', function () {
    $object = new ObjectType(SampleUserModel::class);

    $originalType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($object, 'getOriginal', [new LiteralStringType('id')])
        );

    expect($originalType->toString())->toBe('int');
});

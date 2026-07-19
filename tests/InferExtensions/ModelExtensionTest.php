<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
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

it('allows overriding a relation type using PHPDoc', function () {
    $cd = $this->infer->analyzeClass(ModelExtensionTest_ModelWithRelationOverride::class);

    $propertyType = (new ObjectType(ModelExtensionTest_ModelWithRelationOverride::class))
        ->getPropertyType('user');

    expect($propertyType->toString())->toBe(ModelExtensionTest_OverriddenUser::class);
});

/** @property ModelExtensionTest_OverriddenUser $user */
class ModelExtensionTest_ModelWithRelationOverride extends SamplePostModel {}

class ModelExtensionTest_OverriddenUser extends Model {}

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

it('infers only method return type', function () {
    $this->infer->analyzeClass(SampleUserModel::class);

    $scope = new Infer\Scope\GlobalScope;
    $scope->index = $this->infer->index;

    $onlyReturnType = (new ObjectType(SampleUserModel::class))
        ->getMethodReturnType('only', scope: $scope, arguments: [
            new KeyedArrayType([
                new ArrayItemType_(0, new LiteralStringType('name')),
                new ArrayItemType_(1, new LiteralStringType('email')),
            ]),
        ]);

    expect(collect($onlyReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key => $t->value->toString()]))
        ->toMatchArray([
            'name' => 'string',
            'email' => 'string',
        ]);
});

it('infers except method return type', function () {
    $this->infer->analyzeClass(SampleUserModel::class);

    $scope = new Infer\Scope\GlobalScope;
    $scope->index = $this->infer->index;

    $exceptReturnType = ReferenceTypeResolver::getInstance()->resolve(
        $scope,
        (new ObjectType(SampleUserModel::class))
            ->getMethodReturnType('except', scope: $scope, arguments: [
                new KeyedArrayType([
                    new ArrayItemType_(0, new LiteralStringType('password')),
                ]),
            ]),
    );

    expect(collect($exceptReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key => $t->value->toString()]))
        ->not->toHaveKey('password')
        ->and(collect($exceptReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key => $t->value->toString()]))
        ->toHaveKeys(['id', 'name', 'email']);
});

it('infers only with array keys argument via toHaveType', function () {
    $type = getStatementType('(new '.SampleUserModel::class.')->only(["name", "email"])');

    expect($type->toString())->toBe('array{name: string, email: string}');
});

it('infers except with array keys argument via toHaveType', function () {
    $type = getStatementType('(new '.SampleUserModel::class.')->except(["password"])');

    expect($type->toString())->not->toContain('password')
        ->and($type->toString())->toContain('name: string');
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

it('uses custom cast get definition return type for model attributes', function () {
    $this->infer->analyzeClass(ModelExtensionTest_CustomCastModel::class);

    $object = new ObjectType(ModelExtensionTest_CustomCastModel::class);

    expect($object->getPropertyType('title')->toString())
        ->toBe(ModelExtensionTest_CustomCastValue::class);
});

it('uses custom cast get PHPDoc return type for model attributes', function () {
    $this->infer->configure()->buildDefinitionsUsingReflectionFor([
        ModelExtensionTest_CustomPhpDocCast::class,
    ]);
    $this->infer->analyzeClass(ModelExtensionTest_CustomPhpDocCastModel::class);

    $object = new ObjectType(ModelExtensionTest_CustomPhpDocCastModel::class);

    expect($object->getPropertyType('body')->toString())
        ->toBe('array<string, '.ModelExtensionTest_CustomCastValue::class.'>');
});

class ModelExtensionTest_CustomCastModel extends SamplePostModel
{
    protected $casts = [
        'title' => ModelExtensionTest_CustomCast::class.':with-arguments',
    ];
}

class ModelExtensionTest_CustomPhpDocCastModel extends SamplePostModel
{
    protected $casts = [
        'body' => ModelExtensionTest_CustomPhpDocCast::class,
    ];
}

/** @implements CastsAttributes<ModelExtensionTest_CustomCastValue, mixed> */
class ModelExtensionTest_CustomCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ModelExtensionTest_CustomCastValue
    {
        return new ModelExtensionTest_CustomCastValue;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}

/** @implements CastsAttributes<array<string, ModelExtensionTest_CustomCastValue>, mixed> */
class ModelExtensionTest_CustomPhpDocCast implements CastsAttributes
{
    /** @return array<string, ModelExtensionTest_CustomCastValue> */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}

class ModelExtensionTest_CustomCastValue {}

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

        // TODO: Fails due to role model having invalid PHPDoc, `?\Illuminate\Support\Carbon` should be `\Illuminate\Support\Carbon|null`
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

it('toResource resolves resource type from UseResource attribute', function () {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                new ObjectType(ModelExtensionTest_AttributeModel::class),
                'toResource',
                []
            )
        );

    expect($type->toString())->toBe(ModelExtensionTest_AttributeResource::class.'<'.ModelExtensionTest_AttributeModel::class.'>');
})->skip(fn () => ! class_exists(UseResource::class));

#[UseResource(ModelExtensionTest_AttributeResource::class)]
class ModelExtensionTest_AttributeModel extends Model {}

class ModelExtensionTest_AttributeResource extends \Illuminate\Http\Resources\Json\JsonResource {}

it('toResource resolves resource type by guessing class name', function () {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                new ObjectType(ModelExtensionTest_GuessingModel::class),
                'toResource',
                []
            )
        );

    ModelExtensionTest_GuessingModel::all()->toResourceCollection();

    expect($type->toString())->toBe(ModelExtensionTest_AttributeResource::class.'<'.ModelExtensionTest_GuessingModel::class.'>');
})->skip(fn () => ! method_exists(Model::class, 'toResource'));

class ModelExtensionTest_GuessingModel extends SampleUserModel
{
    public static function guessResourceName(): array
    {
        return [ModelExtensionTest_AttributeResource::class];
    }
}

it('toResource returns instance of explicitly passed resource class', function () {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                new ObjectType(SampleUserModel::class),
                'toResource',
                [new LiteralStringType(ModelExtensionTest_AttributeResource::class)]
            )
        );

    expect($type->toString())->toBe(ModelExtensionTest_AttributeResource::class.'<'.SampleUserModel::class.'>');
})->skip(fn () => ! method_exists(Model::class, 'toResource'));

class Foo_ModelExtensionTest extends Model
{
    public function newCollection(array $models = [])
    {
        return new FooCollection_ModelExtensionTest($models);
    }
}

class FooCollection_ModelExtensionTest extends \Illuminate\Database\Eloquent\Collection {}

class Bar_ModelExtensionTest extends Model
{
    public function foos()
    {
        return $this->hasMany(Foo_ModelExtensionTest::class);
    }
}

class RelationNullabilityOwner_ModelExtensionTest extends Model
{
    protected $table = 'relation_nullability_owners';

    public function requiredModel()
    {
        return $this->hasOne(RelationNullabilityModel_ModelExtensionTest::class, 'required_owner_id');
    }

    public function nullableModel()
    {
        return $this->hasOne(RelationNullabilityModel_ModelExtensionTest::class, 'nullable_owner_id');
    }

    public function nullableModelWithDefault()
    {
        return $this->hasOne(RelationNullabilityModel_ModelExtensionTest::class, 'nullable_owner_id')->withDefault();
    }

    public function nullableModels()
    {
        return $this->hasMany(RelationNullabilityModel_ModelExtensionTest::class, 'nullable_owner_id');
    }
}

class RelationNullabilityModel_ModelExtensionTest extends Model
{
    protected $table = 'relation_nullability_models';

    public function requiredOwner()
    {
        return $this->belongsTo(RelationNullabilityOwner_ModelExtensionTest::class, 'required_owner_id');
    }

    public function nullableOwner()
    {
        return $this->belongsTo(RelationNullabilityOwner_ModelExtensionTest::class, 'nullable_owner_id');
    }

    public function nullableOwnerWithDefault()
    {
        return $this->belongsTo(RelationNullabilityOwner_ModelExtensionTest::class, 'nullable_owner_id')->withDefault();
    }
}

it('infers singular relation nullability from the foreign key column', function () {
    $this->infer->analyzeClass(RelationNullabilityOwner_ModelExtensionTest::class);
    $this->infer->analyzeClass(RelationNullabilityModel_ModelExtensionTest::class);

    $owner = new ObjectType(RelationNullabilityOwner_ModelExtensionTest::class);
    $model = new ObjectType(RelationNullabilityModel_ModelExtensionTest::class);

    expect($owner->getPropertyType('requiredModel')->toString())
        ->toBe(RelationNullabilityModel_ModelExtensionTest::class)
        ->and($owner->getPropertyType('nullableModel')->toString())
        ->toBe(RelationNullabilityModel_ModelExtensionTest::class.'|null')
        ->and($model->getPropertyType('requiredOwner')->toString())
        ->toBe(RelationNullabilityOwner_ModelExtensionTest::class)
        ->and($model->getPropertyType('nullableOwner')->toString())
        ->toBe(RelationNullabilityOwner_ModelExtensionTest::class.'|null');
});

it('keeps singular relations with default models non-nullable', function () {
    $this->infer->analyzeClass(RelationNullabilityOwner_ModelExtensionTest::class);
    $this->infer->analyzeClass(RelationNullabilityModel_ModelExtensionTest::class);

    $owner = new ObjectType(RelationNullabilityOwner_ModelExtensionTest::class);
    $model = new ObjectType(RelationNullabilityModel_ModelExtensionTest::class);

    expect($owner->getPropertyType('nullableModelWithDefault')->toString())
        ->toBe(RelationNullabilityModel_ModelExtensionTest::class)
        ->and($model->getPropertyType('nullableOwnerWithDefault')->toString())
        ->toBe(RelationNullabilityOwner_ModelExtensionTest::class);
});

it('preserves nullable singular relations through nullsafe access', function () {
    $type = getStatementType('(new '.RelationNullabilityModel_ModelExtensionTest::class.')->nullableOwner?->id');

    expect($type->toString())->toBe('int|null');
});

it('keeps collection relations non-nullable when their foreign key is nullable', function () {
    $this->infer->analyzeClass(RelationNullabilityOwner_ModelExtensionTest::class);

    $owner = new ObjectType(RelationNullabilityOwner_ModelExtensionTest::class);

    expect($owner->getPropertyType('nullableModels')->toString())
        ->toBe('Illuminate\\Database\\Eloquent\\Collection<int, '.RelationNullabilityModel_ModelExtensionTest::class.'>');
});

it('uses custom collection type from newCollection for hasMany relations', function () {
    $this->infer->analyzeClass(Foo_ModelExtensionTest::class);
    $this->infer->analyzeClass(Bar_ModelExtensionTest::class);

    $object = new ObjectType(Bar_ModelExtensionTest::class);

    expect($object->getPropertyType('foos')->toString())
        ->toBe(FooCollection_ModelExtensionTest::class.'<int, '.Foo_ModelExtensionTest::class.'>');
});

it('uses custom collection type from newCollection for query get', function () {
    $this->infer->analyzeClass(Foo_ModelExtensionTest::class);

    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType(
                new MethodCallReferenceType(
                    new ObjectType(Foo_ModelExtensionTest::class),
                    'query',
                    []
                ),
                'get',
                []
            )
        );

    expect($type->toString())
        ->toBe(FooCollection_ModelExtensionTest::class.'<int, '.Foo_ModelExtensionTest::class.'>');
});

it('uses custom collection type from newCollection for all', function () {
    $this->infer->analyzeClass(Foo_ModelExtensionTest::class);

    $type = getStatementType(Foo_ModelExtensionTest::class.'::all()');

    expect($type->toString())
        ->toBe(FooCollection_ModelExtensionTest::class.'<int, '.Foo_ModelExtensionTest::class.'>');
});

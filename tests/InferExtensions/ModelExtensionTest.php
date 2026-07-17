<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
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

class ModelExtensionTest_Invoice extends Model {}

class ModelExtensionTest_InvoiceController
{
    public function display(ModelExtensionTest_Invoice $invoice): ModelExtensionTest_Invoice
    {
        $invoice->load([
            'customer',
            'lineItems',
        ]);

        return $invoice;
    }
}

it('tracks relations loaded on a model parameter returned from a method', function () {
    $type = ReferenceTypeResolver::getInstance()->resolve(
        new Infer\Scope\GlobalScope,
        new MethodCallReferenceType(
            new ObjectType(ModelExtensionTest_InvoiceController::class),
            'display',
            [new ObjectType(ModelExtensionTest_Invoice::class)],
        ),
    );

    $modelType = (new TypeWalker)->first(
        $type,
        fn ($type) => $type->isInstanceOf(ModelExtensionTest_Invoice::class),
    );

    expect($modelType)->not->toBeNull();

    $relationsType = ReferenceTypeResolver::getInstance()->resolve(
        new Infer\Scope\GlobalScope,
        new PropertyFetchReferenceType($modelType, 'relations'),
    );

    expect($relationsType->toString())->toBe('list{string(customer), string(lineItems)}');
});

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

class FooBuilder_ModelExtensionTest extends Builder
{
    public function published(): static
    {
        return $this;
    }

    public function visibleTo(SampleUserModel $user): self
    {
        if (mt_rand(0, 1)) {
            return $this;
        }

        return $this->where('user_id', $user->id);
    }
}

class ModelWithCustomBuilder_ModelExtensionTest extends Model
{
    public function newEloquentBuilder($query)
    {
        return new FooBuilder_ModelExtensionTest($query);
    }
}

#[UseEloquentBuilder(FooBuilder_ModelExtensionTest::class)]
class ModelWithCustomBuilderAttribute_ModelExtensionTest extends Model {}

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

it('uses custom query builder type from newEloquentBuilder', function () {
    $this->infer->analyzeClass(ModelWithCustomBuilder_ModelExtensionTest::class);

    expect(getStatementType(ModelWithCustomBuilder_ModelExtensionTest::class.'::query()')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilder_ModelExtensionTest::class.'>')
        ->and(getStatementType('(new '.ModelWithCustomBuilder_ModelExtensionTest::class.')->newQuery()')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilder_ModelExtensionTest::class.'>')
        ->and(getStatementType(ModelWithCustomBuilder_ModelExtensionTest::class.'::published()')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilder_ModelExtensionTest::class.'>');
});

it('preserves custom query builder generics for self-returning methods', function () {
    expect(getStatementType(ModelWithCustomBuilder_ModelExtensionTest::class.'::query()->visibleTo(new '.SampleUserModel::class.'())')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilder_ModelExtensionTest::class.'>')
        ->and(getStatementType(ModelWithCustomBuilder_ModelExtensionTest::class.'::visibleTo(new '.SampleUserModel::class.'())')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilder_ModelExtensionTest::class.'>');
});

it('uses custom query builder type from UseEloquentBuilder', function () {
    $this->infer->analyzeClass(ModelWithCustomBuilderAttribute_ModelExtensionTest::class);

    expect(getStatementType(ModelWithCustomBuilderAttribute_ModelExtensionTest::class.'::query()')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilderAttribute_ModelExtensionTest::class.'>')
        ->and(getStatementType(ModelWithCustomBuilderAttribute_ModelExtensionTest::class.'::query()->visibleTo(new '.SampleUserModel::class.'())')->toString())
        ->toBe(FooBuilder_ModelExtensionTest::class.'<'.ModelWithCustomBuilderAttribute_ModelExtensionTest::class.'>');
})->skip(fn () => ! class_exists(UseEloquentBuilder::class));

it('uses custom collection type from newCollection for all', function () {
    $this->infer->analyzeClass(Foo_ModelExtensionTest::class);

    $type = getStatementType(Foo_ModelExtensionTest::class.'::all()');

    expect($type->toString())
        ->toBe(FooCollection_ModelExtensionTest::class.'<int, '.Foo_ModelExtensionTest::class.'>');
});

<?php

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EloquentCollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Tests\files\PendingUnknownWithSelfReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use function Spatie\Snapshots\assertMatchesSnapshot;
use function Spatie\Snapshots\assertMatchesTextSnapshot;

uses(RefreshDatabase::class);

it('gets json resource type', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(InferTypesTest_SampleJsonResource::class);

    $returnType = $type->getMethodCallType('toArray');

    assertMatchesTextSnapshot($returnType->toString());
});


it('gets correct type of pending self reference and unknown', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(PendingUnknownWithSelfReference::class);

    expect($type->methods['returnSomeCall']->toString())
        ->toBe('(): unknown')
        ->and($type->methods['returnThis']->toString())
        ->toBe("(): ".PendingUnknownWithSelfReference::class);
});

it('gets json resource type with enum', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(InferTypesTest_SampleTwoPostJsonResource::class);

    $returnType = $type->getMethodCallType('toArray');

    assertMatchesTextSnapshot($returnType->toString());
});

it('simply infers method types', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(Foo_SampleClass::class);

    $returnType = $type->getMethodCallType('bar');

    expect($returnType->toString())->toBe('int(1)');
});

it('infers method types for methods declared not in order', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooTwo_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('(): int(1)');
});

it('infers method types for methods in array', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooFour_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('array{a: int(1)}');
});

it('infers unknown method call', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooThree_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('array{a: array{p: unknown}, b: unknown}');
});

it('infers cast', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooFive_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('int');
});

it('infers cyclic dep', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooSix_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('unknown|int(1)');
});

it('infers this return', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(FooSeven_SampleClass::class);

    $returnType = $type->getMethodCallType('foo');

    expect($returnType->toString())->toBe('FooSeven_SampleClass');
});

it('infers model type', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        ModelToSchema::class,
        EloquentCollectionToSchema::class,
    ]);
    $extension = new ModelToSchema($infer, $transformer, $components);

    $type = new ObjectType(InferTypesTest_SamplePostModel::class);
    $openApiType = $extension->toSchema($type);

    expect($components->schemas)->toHaveLength(2)->toHaveKeys(['InferTypesTest_SamplePostModel', 'InferTypesTest_SampleModel']);
    assertMatchesSnapshot($openApiType->toArray());
});

it('infers model type when toArray is implemented', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        ModelToSchema::class,
        EloquentCollectionToSchema::class,
    ]);
    $extension = new ModelToSchema($infer, $transformer, $components);

    $type = new ObjectType(InferTypesTest_SamplePostModelWithToArray::class);
    $openApiType = $extension->toSchema($type);

    expect($components->schemas)->toHaveLength(2)->toHaveKeys(['InferTypesTest_SamplePostModelWithToArray', 'InferTypesTest_SampleModel']);
    assertMatchesSnapshot($openApiType->toArray());
});

class FooSeven_SampleClass
{
    public function foo()
    {
        return $this;
    }
}

class FooSix_SampleClass
{
    public function foo()
    {
        if (piu()) {
            return 1;
        }

        return $this->foo();
    }
}

class FooFive_SampleClass
{
    public function foo()
    {
        return (int) $a;
    }
}

class FooFour_SampleClass
{
    public function foo()
    {
        return ['a' => $this->bar()];
    }

    public function bar()
    {
        return 1;
    }
}

class FooThree_SampleClass
{
    public function foo()
    {
        return [
            'a' => $this->bar(),
            'b' => $this->someMethod(),
        ];
    }

    public function bar()
    {
        return ['p' => $a];
    }
}

class FooTwo_SampleClass
{
    public function foo()
    {
        return fn () => $this->bar();
    }

    public function bar()
    {
        return 1;
    }
}

class Foo_SampleClass
{
    public function bar()
    {
        return 1;
    }

    public function foo()
    {
        return $this->bar();
    }
}

class InferTypesTest_SampleClass
{
    public function wow($request)
    {
        return new BrandEdge($request);
    }
}

/**
 * @property InferTypesTest_SampleModel $resource
 */
class InferTypesTest_SampleJsonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            $this->merge(fn () => ['foo' => 'bar']),
            $this->mergeWhen(true, fn () => ['foo' => 'bar', 'id_inside' => $this->resource->id]),
            'when' => $this->when(true, ['wiw']),
            'item' => new InferTypesTest_SampleTwoJsonResource($this->resource),
            'item_make' => InferTypesTest_SampleTwoJsonResource::make($this->resource),
            'items' => InferTypesTest_SampleTwoJsonResource::collection($this->resource),
            'optional_when_new' => $this->when(true, fn () => new InferTypesTest_SampleTwoJsonResource($this->resource)),
            $this->mergeWhen(true, fn () => [
                'threads' => [
                    $this->mergeWhen(true, fn () => [
                        'brand' => new InferTypesTest_SampleTwoJsonResource(null),
                    ]),
                ],
            ]),
            '_test' => 1,
            /** @var int $with_doc great */
            'with_doc' => $this->foo,
            /** @var string wow this is good */
            'when_with_doc' => $this->when(true, 'wiw'),
            'some' => $this->some,
            'id' => $this->id,
            'email' => $this->resource->email,
        ];
    }
}

/**
 * @property InferTypesTest_SampleModel $resource
 */
class InferTypesTest_SampleTwoJsonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'email' => $this->resource->email,
        ];
    }
}

/**
 * @property InferTypesTest_SamplePostModel $resource
 */
class InferTypesTest_SampleTwoPostJsonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
        ];
    }
}

class InferTypesTest_SampleModel extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'users';
}

class InferTypesTest_SamplePostModel extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'posts';

    protected $with = ['parent', 'children', 'user'];

    protected $casts = [
        'status' => Status::class,
    ];

    public function getReadTimeAttribute()
    {
        return 123;
    }

    public function parent()
    {
        return $this->belongsTo(InferTypesTest_SamplePostModel::class);
    }

    public function children()
    {
        return $this->hasMany(InferTypesTest_SamplePostModel::class);
    }

    public function user()
    {
        return $this->belongsTo(InferTypesTest_SampleModel::class, 'user_id');
    }
}

class InferTypesTest_SamplePostModelWithToArray extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'posts';

    protected $casts = [
        'status' => Status::class,
    ];

    public function getReadTimeAttribute()
    {
        return 123;
    }

    public function parent()
    {
        return $this->belongsTo(InferTypesTest_SamplePostModelWithToArray::class);
    }

    public function children()
    {
        return $this->hasMany(InferTypesTest_SamplePostModelWithToArray::class);
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'children' => $this->children,
            'read_time' => $this->read_time,
            'user' => $this->user,
            'created_at' => $this->created_at,
        ];
    }

    public function user()
    {
        return $this->belongsTo(InferTypesTest_SampleModel::class, 'user_id');
    }
}

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

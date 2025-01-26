<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\CollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SamplePostModelWithToArray;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;

use function Spatie\Snapshots\assertMatchesSnapshot;
use function Spatie\Snapshots\assertMatchesTextSnapshot;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->infer = app(Infer::class);
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
});

it('gets json resource type', function () {
    $def = $this->infer->analyzeClass(InferTypesTest_SampleJsonResource::class);

    $returnType = $def->getMethodDefinition('toArray')->type->getReturnType();

    assertMatchesTextSnapshot($returnType->toString());
});

it('gets json resource type with enum', function () {
    $def = $this->infer->analyzeClass(InferTypesTest_SampleTwoPostJsonResource::class);

    $returnType = $def->getMethodDefinition('toArray')->type->getReturnType();

    assertMatchesTextSnapshot($returnType->toString());
});

it('infers model type', function () {
    $transformer = new TypeTransformer($this->infer, $this->context, [
        ModelToSchema::class,
        CollectionToSchema::class,
    ]);
    $extension = new ModelToSchema($this->infer, $transformer, $this->components, $this->context);

    $type = new ObjectType(SamplePostModel::class);
    $openApiType = $extension->toSchema($type);

    expect($this->components->schemas)->toHaveLength(2)->toHaveKeys(['SamplePostModel', 'SampleUserModel']);
    assertMatchesSnapshot($openApiType->toArray());
});

it('infers model type when toArray is implemented', function () {
    $transformer = new TypeTransformer($infer = $this->infer, $this->context, [
        ModelToSchema::class,
        CollectionToSchema::class,
    ]);
    $extension = new ModelToSchema($infer, $transformer, $this->components, $this->context);

    $type = new ObjectType(SamplePostModelWithToArray::class);
    $openApiType = $extension->toSchema($type);

    expect($this->components->schemas)->toHaveLength(2)->toHaveKeys(['SamplePostModelWithToArray', 'SampleUserModel']);
    assertMatchesSnapshot($openApiType->toArray());
});

/**
 * @property SampleUserModel $resource
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
 * @property SamplePostModel $resource
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
 * @property SamplePostModel $resource
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

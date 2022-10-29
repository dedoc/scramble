<?php

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use function Spatie\Snapshots\assertMatchesSnapshot;

test('transforms collection with toArray only', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $components);

    $type = new ObjectType(UserCollection_One::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});
class UserCollection_One extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = UserResource::class;

    public function toArray($request)
    {
        return [
            $this->merge(['foo' => 'bar']),
            'users' => $this->collection,
            'meta' => [
                'foo' => 'bar',
            ],
        ];
    }
}

test('transforms collection with toArray and with', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $components);

    $type = new ObjectType(UserCollection_Two::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});
class UserCollection_Two extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = UserResource::class;

    public function toArray($request)
    {
        return [
            $this->merge(['foo' => 'bar']),
            'users' => $this->collection,
            'meta' => [
                'foo' => 'bar',
            ],
        ];
    }

    public function with($request)
    {
        return [
            'some' => 'data',
        ];
    }
}

test('transforms collection without proper toArray implementation', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $components);

    $type = new ObjectType(UserCollection_Three::class);

    assertMatchesSnapshot([
        'response' => $extension->toResponse($type)->toArray(),
        'components' => $components->toArray(),
    ]);
});
class UserCollection_Three extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = UserResource::class;

    public function toArray($request)
    {
        return parent::toArray($request);
    }
}

test('transforms collection without toArray implementation', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $components);

    $type = new ObjectType(UserCollection_Four::class);

    assertMatchesSnapshot([
        'response' => $extension->toResponse($type)->toArray(),
        'components' => $components->toArray(),
    ]);
});
class UserCollection_Four extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = UserResource::class;
}

class UserResource extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => 1,
        ];
    }
}

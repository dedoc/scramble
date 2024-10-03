<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\LengthAwarePaginatorTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

it('correctly documents when annotated', function () {
    $type = new Generic(LengthAwarePaginator::class, [
        new ObjectType(LengthAwarePaginatorTypeToSchemaTest_Resource::class),
    ]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class,
        LengthAwarePaginatorTypeToSchema::class,
    ]);
    $extension = new LengthAwarePaginatorTypeToSchema($infer, $transformer, $components);

    expect($extension->shouldHandle($type))->toBeTrue();
    expect($extension->toResponse($type)->toArray())->toMatchSnapshot();
});

class LengthAwarePaginatorTypeToSchemaTest_Resource extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => 1];
    }
}

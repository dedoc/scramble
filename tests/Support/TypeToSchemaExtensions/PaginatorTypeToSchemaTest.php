<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatorTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;

it('correctly documents when annotated', function () {
    $type = new Generic(Paginator::class, [
        new ObjectType(PaginatorTypeToSchemaTest_Resource::class),
    ]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class,
        PaginatorTypeToSchema::class,
    ]);
    $extension = new PaginatorTypeToSchema($infer, $transformer, $components);

    expect($extension->shouldHandle($type))->toBeTrue();
    expect($extension->toResponse($type)->toArray())->toMatchSnapshot();
});

class PaginatorTypeToSchemaTest_Resource extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => 1];
    }
}

<?php

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatorTypeToSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
});

it('correctly documents when annotated', function () {
    $type = new Generic(Paginator::class, [
        new ObjectType(PaginatorTypeToSchemaTest_Resource::class),
    ]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
        PaginatorTypeToSchema::class,
    ]);
    $extension = new PaginatorTypeToSchema($infer, $transformer, $this->components, $this->context);

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

it('uses SchemaName attribute value in response description', function () {
    $type = new Generic(Paginator::class, [
        new ObjectType(PaginatorTypeToSchemaTest_ResourceWithSchemaName::class),
    ]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
        PaginatorTypeToSchema::class,
    ]);
    $extension = new PaginatorTypeToSchema($infer, $transformer, $this->components, $this->context);

    $response = $extension->toResponse($type)->toArray();

    expect($response['description'])
        ->toBe('Paginated set of `PaginatorSchemaName`')
        ->and($response['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null)
        ->toBe('#/components/schemas/PaginatorSchemaName');
});
#[SchemaName('PaginatorSchemaName')]
class PaginatorTypeToSchemaTest_ResourceWithSchemaName extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => 1];
    }
}

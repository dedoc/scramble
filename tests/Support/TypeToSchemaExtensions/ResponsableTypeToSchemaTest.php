<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponsableTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        ResponsableTypeToSchema::class,
        ResponseTypeToSchema::class,
    ]);
});

it('handles responsable types', function () {
    $extension = new ResponsableTypeToSchema(
        app(Infer::class),
        $this->transformer,
        $this->components,
    );

    expect($extension->shouldHandle(new ObjectType(ResponsableTypeToSchemaTest_JsonResponsable::class)))->toBeTrue()
        ->and($extension->shouldHandle(new ObjectType(\stdClass::class)))->toBeFalse();
});

class ResponsableTypeToSchemaTest_JsonResponsable implements Responsable
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'id' => 42,
            'name' => 'foo',
        ]);
    }
}

it('transforms responsable returning json response', function () {
    $type = new ObjectType(ResponsableTypeToSchemaTest_JsonResponsable::class);

    $response = $this->transformer->toResponse($type);

    expect($response->code)->toBe(200)
        ->and($response->toArray()['content']['application/json']['schema'])->toBe([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'const' => 42],
                'name' => ['type' => 'string', 'const' => 'foo'],
            ],
            'required' => ['id', 'name'],
        ]);
});

it('transforms responsable returning response with custom status code', function () {
    $type = new ObjectType(ResponsableTypeToSchemaTest_NoContentResponsable::class);

    $response = $this->transformer->toResponse($type);

    expect($response->code)->toBe(204)
        ->and($response->toArray()['description'])->toBe('No content')
        ->and($response->toArray()['content'] ?? null)->toBeNull();
});

class ResponsableTypeToSchemaTest_NoContentResponsable implements Responsable
{
    public function toResponse($request): Response
    {
        return response('', 204);
    }
}

it('transforms responsable returning json response with custom status code', function () {
    $type = new ObjectType(ResponsableTypeToSchemaTest_CreatedResponsable::class);

    $response = $this->transformer->toResponse($type);

    expect($response->code)->toBe(201)
        ->and($response->toArray()['content']['application/json']['schema'])->toBe([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'const' => 1],
            ],
            'required' => ['id'],
        ]);
});

class ResponsableTypeToSchemaTest_CreatedResponsable implements Responsable
{
    public function toResponse($request): JsonResponse
    {
        return response()->json(['id' => 1], 201);
    }
}

it('documents responsable return type from controller', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/responsable', function () {
        return new ResponsableTypeToSchemaTest_JsonResponsable;
    }));

    expect($openApiDocument['paths']['/responsable']['get']['responses']['200']['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'const' => 42],
                'name' => ['type' => 'string', 'const' => 'foo'],
            ],
            'required' => ['id', 'name'],
        ]);
});

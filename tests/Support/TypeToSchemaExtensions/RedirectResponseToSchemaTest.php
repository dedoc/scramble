<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\RedirectResponseToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponsableTypeToSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function () {
    $this->components = new Components;
    $this->context = new OpenApiContext((new OpenApi('3.1.0'))->setComponents($this->components), new GeneratorConfig);
    $this->transformer = new TypeTransformer(app(Infer::class), $this->context, [
        ResponsableTypeToSchema::class,
        RedirectResponseToSchema::class,
    ]);
});

it('transforms redirect response to a 302 response with location header', function () {
    $response = $this->transformer->toResponse(new ObjectType(RedirectResponse::class));

    expect($response->code)->toBe(302)
        ->and($response->toArray())->not->toHaveKey('content')
        ->and($response->toArray()['headers']['Location'])->toBe([
            'required' => true,
            'schema' => ['type' => 'string'],
        ]);
});

it('documents redirect returned from controller', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/redirect', function (): RedirectResponse {
        return redirect()->away('https://example.com');
    }));

    expect($openApiDocument['paths']['/redirect']['get']['responses'])->toHaveKey('302')
        ->and($openApiDocument['paths']['/redirect']['get']['responses']['302']['headers'])->toHaveKey('Location');
});

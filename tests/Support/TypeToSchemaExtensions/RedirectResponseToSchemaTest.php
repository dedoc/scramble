<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
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

it('transforms a plain redirect object to a 302 response with location header', function (string $class) {
    $response = $this->transformer->toResponse(new ObjectType($class));

    expect($response->code)->toBe(302)
        ->and($response->toArray())->not->toHaveKey('content')
        ->and($response->toArray()['headers']['Location'])->toBe([
            'required' => true,
            'schema' => ['type' => 'string'],
        ]);
})->with([
    [RedirectResponse::class],
    [\Symfony\Component\HttpFoundation\RedirectResponse::class],
]);

it('transforms inferred redirect status with location header', function (string $expression, int $expectedStatus) {
    $response = $this->transformer->toResponse(getStatementType($expression));

    expect($response->code)->toBe($expectedStatus)
        ->and($response->toArray())->not->toHaveKey('content')
        ->and($response->toArray()['headers']['Location'])->toBe([
            'required' => true,
            'schema' => ['type' => 'string'],
        ]);
})->with([
    ["redirect('/home')", 302],
    ["redirect('/home', 301)", 301],
    ['back(303)', 303],
    ["to_route('home', status: 307)", 307],
    ["redirect()->away('https://example.com')", 302],
    ["redirect()->away('https://example.com', status: 308)", 308],
    ["redirect()->route('home', status: 303)", 303],
    ["response()->redirectTo('/home', 301)", 301],
    ["response()->redirectToRoute('home', status: 307)", 307],
    ["new Illuminate\Http\RedirectResponse('/home', 303)", 303],
    ["new Symfony\Component\HttpFoundation\RedirectResponse('/home', 308)", 308],
    ["redirect()->away('https://example.com')->setStatusCode(301)", 301],
]);

it('defaults to 302 when the inferred status is not a literal', function () {
    $type = new Generic(RedirectResponse::class, [
        new LiteralStringType(''),
        new IntegerType,
        new ArrayType,
    ]);

    $response = $this->transformer->toResponse($type);

    expect($response->code)->toBe(302)
        ->and($response->toArray())->not->toHaveKey('content')
        ->and($response->toArray()['headers']['Location']['required'])->toBeTrue();
});

it('documents a dynamic redirect status as 302', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/redirect', function (): RedirectResponse {
        return redirect()->away('https://example.com', request()->integer('status'));
    }));

    $responses = $openApiDocument['paths']['/redirect']['get']['responses'];

    expect($responses)->toHaveKey('302')->not->toHaveKey('200')
        ->and($responses['302'])->not->toHaveKey('content')
        ->and($responses['302']['headers']['Location']['required'])->toBeTrue();
});

it('documents redirect returned from controller', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/redirect', function (): RedirectResponse {
        return redirect()->away('https://example.com');
    }));

    expect($openApiDocument['paths']['/redirect']['get']['responses'])->toHaveKey('302')
        ->and($openApiDocument['paths']['/redirect']['get']['responses']['302']['headers'])->toHaveKey('Location');
});

it('documents an explicit redirect status returned from controller', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/redirect', function (): RedirectResponse {
        return redirect()->away('https://example.com', status: 307);
    }));

    expect($openApiDocument['paths']['/redirect']['get']['responses'])->toHaveKey('307')->not->toHaveKey('302')
        ->and($openApiDocument['paths']['/redirect']['get']['responses']['307']['headers'])->toHaveKey('Location');
});

it('documents a chained redirect status returned from controller', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('api/redirect', function (): RedirectResponse {
        return response()->redirectTo('/home')->setStatusCode(308);
    }));

    expect($openApiDocument['paths']['/redirect']['get']['responses'])->toHaveKey('308')->not->toHaveKey('302')
        ->and($openApiDocument['paths']['/redirect']['get']['responses']['308'])->not->toHaveKey('content');
});

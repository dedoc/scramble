<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('documents abort helper with 404 status as referenced error response', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [AbortHelpersResponseDocumentation_Test::class, 'abort_404']);
    });

    expect($response = $openApiDocument['paths']['/test']['post']['responses'][404])
        ->and($response)
        ->toHaveKey('$ref', '#/components/responses/ModelNotFoundException');
});

it('documents abort helper as not referenced error response', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [AbortHelpersResponseDocumentation_Test::class, 'abort']);
    });

    expect($response = $openApiDocument['paths']['/test']['post']['responses'][400])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.properties.message.example', 'Something is wrong.');
});

it('documents abort_if helper', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [AbortHelpersResponseDocumentation_Test::class, 'abort_if']);
    });

    expect($response = $openApiDocument['paths']['/test']['post']['responses'][402])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.properties.message.example', 'Something is wrong.');
});

it('documents abort_unless helper', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [AbortHelpersResponseDocumentation_Test::class, 'abort_unless']);
    });

    expect($response = $openApiDocument['paths']['/test']['post']['responses'][403])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.properties.message.example', 'Something is wrong.');
});

class AbortHelpersResponseDocumentation_Test extends \Illuminate\Routing\Controller
{
    public function abort_404()
    {
        abort(404, 'Something is wrong.');
    }

    public function abort()
    {
        abort(400, 'Something is wrong.');
    }

    public function abort_if()
    {
        abort_if(rand(0, 1) > 0, 402, 'Something is wrong.');
    }

    public function abort_unless()
    {
        abort_unless(rand(0, 1) > 0, 403, 'Something is wrong.');
    }
}

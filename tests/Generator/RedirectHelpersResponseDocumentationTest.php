<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('documents redirect helper basic response', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [RedirectHelpersResponseDocumentation_Test::class, 'redirect']);
    });

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][302])
        ->toHaveKey('description')
        ->and($response)
        ->not->toHaveKey('$ref');
});

it('documents redirect helper to route', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [RedirectHelpersResponseDocumentation_Test::class, 'redirect_to']);
    });

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][302])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.example', '/dashboard');
});

it('documents redirect helper to route with additional status', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [RedirectHelpersResponseDocumentation_Test::class, 'redirect_307']);
    });

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][307])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.example', '/dashboard');
});

it('documents redirect helper with conditions', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [RedirectHelpersResponseDocumentation_Test::class, 'redirect_conditional']);
    });

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][302])
        ->toHaveKey('description')
        ->toHaveKey('content')
        ->and($response)
        ->not->toHaveKey('$ref')
        ->toHaveKey('content.application/json.schema.anyOf.0.example', '/dashboard')
        ->toHaveKey('content.application/json.schema.anyOf.1.example', '/');
});

class RedirectHelpersResponseDocumentation_Test extends \Illuminate\Routing\Controller
{
    public function redirect()
    {
        return redirect();
    }

    public function redirect_to()
    {
        return redirect('/dashboard');
    }

    public function redirect_307()
    {
        return redirect('/dashboard', 307);
    }

    public function redirect_conditional()
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }

        return redirect('/');
    }
}

<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('adds an alternative server to operation when no matching server found', function () {
    $openApiDocument = generateForRoute(function () {
        RouteFacade::post('api/test', [AlternativeServers_Test::class, 'a']);

        return RouteFacade::domain('{param}.localhost')->get('api/test', [AlternativeServers_Test::class, 'a']);
    });

    expect($alternativeServers = $openApiDocument['paths']['/test']['get']['servers'] ?? [])->toHaveCount(1)
        ->and($alternativeServers[0]['url'])->toBe('http://{param}.localhost/api')
        ->and($openApiDocument['paths']['/test']['servers'] ?? [])->toBeEmpty();
});

it('doesnt add an alternative server when there is matching server', function () {
    config()->set('scramble.servers', [
        'Live' => 'http://{param}.localhost/api',
    ]);
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::domain('{param}.localhost')->get('api/test', [AlternativeServers_Test::class, 'a']);
    });
    config()->set('scramble.servers', null);

    expect($openApiDocument['paths']['/test']['get']['servers'] ?? [])->toHaveCount(0)
        ->and($openApiDocument['paths']['/test']['servers'] ?? [])->toHaveCount(0);
});

it('adds an alternative server when there is matching and not matching servers', function () {
    config()->set('scramble.servers', [
        'Demo' => 'http://localhost/api',
        'Live' => 'http://{param}.localhost/api',
    ]);
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::domain('{param}.localhost')->get('api/test', [AlternativeServers_Test::class, 'a']);
    });
    config()->set('scramble.servers', null);

    expect($alternativeServers = $openApiDocument['paths']['/test']['servers'] ?? [])->toHaveCount(1)
        ->and($alternativeServers[0])->toBe([
            'url' => 'http://{param}.localhost/api',
            'description' => 'Live',
            'variables' => [
                'param' => [
                    'default' => 'example',
                ],
            ],
        ]);
});

it('alternative server is moved to paths when all path operations have it', function () {
    $openApiDocument = generateForRoute(function () {
        RouteFacade::domain('{param}.localhost')->post('api/test', [AlternativeServers_Test::class, 'a']);

        return RouteFacade::domain('{param}.localhost')->get('api/test', [AlternativeServers_Test::class, 'a']);
    });

    expect($openApiDocument['paths']['/test']['servers'] ?? [])->toHaveCount(1);
});

class AlternativeServers_Test extends \Illuminate\Routing\Controller
{
    public function a() {}
}

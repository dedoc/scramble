<?php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * @param  list<string>  $uris
 * @param  array<string, mixed>  $configOverrides
 */
function generateForRouteUris(array $uris, array $configOverrides = []): array
{
    $config = Scramble::configure()
        ->useConfig(array_merge(config('scramble'), [
            'security_strategy' => MiddlewareAuthSecurityStrategy::class,
        ], $configOverrides))
        ->routes(fn (Route $r) => in_array($r->uri, $uris, true));

    return app()->make(\Dedoc\Scramble\Generator::class)($config);
}

it('does not document security when no route has auth middleware', function () {
    RouteFacade::get(
        'api/public',
        [MiddlewareAuthSecurityStrategyTest_PublicController::class, 'index'],
    );

    $openApiDocument = generateForRouteUris(['api/public']);

    expect($openApiDocument)->not->toHaveKey('security')
        ->and($openApiDocument['paths']['/public']['get'])->not->toHaveKey('security');
});

it('documents bearer security when a route has auth middleware', function () {
    RouteFacade::get(
        'api/protected',
        [MiddlewareAuthSecurityStrategyTest_ProtectedController::class, 'index'],
    )->middleware('auth:sanctum');

    RouteFacade::get(
        'api/public',
        [MiddlewareAuthSecurityStrategyTest_PublicController::class, 'index'],
    );

    $openApiDocument = generateForRouteUris(['api/protected', 'api/public']);

    expect($openApiDocument['security'])->toBe([['http' => []]])
        ->and($openApiDocument['components']['securitySchemes']['http']['scheme'])->toBe('bearer')
        ->and($openApiDocument['paths']['/protected']['get'])->not->toHaveKey('security')
        ->and($openApiDocument['paths']['/public']['get']['security'])->toBe([]);
});

it('supports custom auth middleware patterns', function () {
    RouteFacade::get(
        'api/protected',
        [MiddlewareAuthSecurityStrategyTest_ProtectedController::class, 'index'],
    )->middleware('api.token');

    $openApiDocument = generateForRouteUris(['api/protected'], [
        'security_strategy' => [
            MiddlewareAuthSecurityStrategy::class,
            ['middleware' => ['api.token']],
        ],
    ]);

    expect($openApiDocument)->toHaveKey('security');
});

it('marks routes as public when they lack auth middleware', function () {
    RouteFacade::get(
        'api/guest',
        [MiddlewareAuthSecurityStrategyTest_PublicController::class, 'index'],
    )->middleware('guest');

    RouteFacade::get(
        'api/protected',
        [MiddlewareAuthSecurityStrategyTest_ProtectedController::class, 'index'],
    )->middleware('auth:sanctum');

    $openApiDocument = generateForRouteUris(['api/guest', 'api/protected']);

    expect($openApiDocument['paths']['/guest']['get']['security'])->toBe([])
        ->and($openApiDocument['paths']['/protected']['get'])->not->toHaveKey('security');
});

it('keeps @unauthenticated routes public even when they have auth middleware', function () {
    RouteFacade::get(
        'api/protected',
        [MiddlewareAuthSecurityStrategyTest_ProtectedController::class, 'index'],
    )->middleware('auth:sanctum');

    RouteFacade::get(
        'api/unauthenticated',
        [MiddlewareAuthSecurityStrategyTest_UnauthenticatedController::class, 'index'],
    )->middleware('auth:sanctum');

    $openApiDocument = generateForRouteUris(['api/protected', 'api/unauthenticated']);

    expect($openApiDocument['paths']['/unauthenticated']['get']['security'])->toBe([]);
});

class MiddlewareAuthSecurityStrategyTest_PublicController
{
    public function index() {}
}

class MiddlewareAuthSecurityStrategyTest_ProtectedController
{
    public function index() {}
}

class MiddlewareAuthSecurityStrategyTest_UnauthenticatedController
{
    /**
     * @unauthenticated
     */
    public function index() {}
}

<?php

namespace Dedoc\Scramble\Tests\Reflection;

use Dedoc\Scramble\Reflection\ReflectionRoute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;

test('create single instance from route', function () {
    $route = RouteFacade::get('_', fn () => null);

    $ra = ReflectionRoute::createFromRoute($route);
    $rb = ReflectionRoute::createFromRoute($route);

    expect($ra === $rb)->toBeTrue();
});

test('gets params aliases single instance from route', function () {
    $route = RouteFacade::get('{test_id}', fn (string $testId) => null);

    expect(ReflectionRoute::createFromRoute($route)->getSignatureParametersMap())->toBe([
        'test_id' => 'testId',
    ]);
});

test('gets params aliases without request from route', function () {
    $route = RouteFacade::get('{test_id}', fn (Request $request, string $testId) => null);

    expect(ReflectionRoute::createFromRoute($route)->getSignatureParametersMap())->toBe([
        'test_id' => 'testId',
    ]);
});

test('gets bound params types', function () {
    $r = ReflectionRoute::createFromRoute(
        RouteFacade::get('{test_id}', fn (Request $request, User_ReflectionRouteTest $testId) => null)
    );

    expect($r->getBoundParametersTypes())->toBe([
        'test_id' => User_ReflectionRouteTest::class,
    ]);
});
class User_ReflectionRouteTest extends Model {}

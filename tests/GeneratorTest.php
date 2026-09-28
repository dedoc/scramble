<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Scramble::routes(fn (Route $route) => $route->uri === 'api/version');

    RouteFacade::get('api/version', function () {
        // Two `var` needed to introduce issue of exploding by `AT` in the route provider.
        /** @var int $bar */
        /** @var int $foo */

        return ['foo' => 'bar'];
    });
});

it('analyzes a closure route without deprecations', function () {
    $deprecations = [];

    set_error_handler(function (int $severity, string $message) use (&$deprecations) {
        if ($severity !== E_DEPRECATED) {
            return false;
        }

        $deprecations[] = $message;

        return true;
    });

    try {
        artisan('scramble:analyze')->assertOk();
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
});

it('generates documentation for a route-cached closure', function () {
    // Mutating registered application's routes so in route provider we get routes with serialized closures.
    // This is just the imitation of `route:cache` command, but without complex setup needed to test it in the package.
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    $spec = app(Generator::class)();

    expect($spec['paths'])->toHaveKey('/version');
});

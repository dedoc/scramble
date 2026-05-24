<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

it('does not clone ui and document routes', function () {
    $ui = fn (Router $router, $action) => $router->get('docs/custom', $action);
    $document = fn (Router $router, $action) => $router->get('docs/custom.json', $action);

    $config = Scramble::configure()->expose(ui: $ui, document: $document);

    $cloned = $config->cloneWithoutExposing();

    expect($cloned->uiRoute)->toBeNull()
        ->and($cloned->documentRoute)->toBeNull();
});

it('clones route resolver', function () {
    $resolver = fn (Route $route) => $route->uri === 'api/test';

    $config = Scramble::configure()->routes($resolver);

    expect($config->cloneWithoutExposing()->routes())->toBe($resolver);
});

it('does not expose registered apis by default', function () {
    Scramble::configure()->expose(
        ui: 'docs/default',
        document: 'docs/default.json',
    );

    $registered = Scramble::registerApi('v2');

    expect($registered->uiRoute)->toBeNull()
        ->and($registered->documentRoute)->toBeNull();
});

<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;

it('clones route resolver', function () {
    $resolver = fn (Route $route) => $route->uri === 'api/test';

    $config = Scramble::configure()->routes($resolver);

    expect($config->cloneWithoutExposing()->routes())->toBe($resolver);
});

it('clones eager load analysis setting', function () {
    $config = (new GeneratorConfig)->withoutEagerLoadAnalysis();

    expect($config->eagerLoadAnalysis())->toBeFalse()
        ->and($config->cloneWithoutExposing()->eagerLoadAnalysis())->toBeFalse()
        ->and($config->withEagerLoadAnalysis()->eagerLoadAnalysis())->toBeTrue();
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

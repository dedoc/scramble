<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function () {
    Scramble::$routeResolver = null;
});

it('includes routes that share same prefix', function () {

    config()->set('scramble.api_path', 'api/v1');

    $fooController = new class
    {
        public function __invoke()
        {
            return 1;
        }
    };

    $included = 'api/v1/foo';

    RouteFacade::post($included, $fooController::class);

    $openApiDocument = app()->make(Generator::class)();

    expect($openApiDocument)->toHaveKey('paths')
        ->and($openApiDocument['paths'])
        ->toHaveCount(1)
        ->and($openApiDocument['paths'])->ToHaveKey('/foo');
});

it('filters routes with different prefix', function () {

    config()->set('scramble.api_path', 'api/v1');

    $fooController = new class
    {
        public function __invoke()
        {
            return 1;
        }
    };

    $included = 'api/v2/foo';

    RouteFacade::post($included, $fooController::class);

    $openApiDocument = app()->make(Generator::class)();

    expect($openApiDocument)->not->toHaveKey('paths');
});

it('includes routes with uri == prefix,', function () {

    config()->set('scramble.api_path', 'api/v1');

    $fooController = new class
    {
        public function __invoke()
        {
            return 1;
        }
    };

    $included = 'api/v1';

    RouteFacade::post($included, $fooController::class);

    $openApiDocument = app()->make(Generator::class)();

    expect($openApiDocument)->toHaveKey('paths')
        ->and($openApiDocument['paths'])
        ->toHaveCount(1)
        ->and($openApiDocument['paths'])->ToHaveKey('/');});

it('filters false positives', function () {

    $fooController = new class
    {
        public function __invoke()
        {
            return 1;
        }
    };

    // starts with api, but not as route prefix
    $excluded = 'apicoltore';

    RouteFacade::post($excluded, $fooController::class);

    $openApiDocument = app()->make(Generator::class)();

    expect($openApiDocument)->not->toHaveKey('paths');
});

<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route as RouteFacade;

it('filters routes with include and exclude api path config', function () {
    Scramble::ignoreDefaultRoutes();

    Scramble::registerApi('filtered', [
        'api_path' => [
            'include' => 'api',
            'exclude' => ['api/internal'],
        ],
    ]);

    RouteFacade::group(['prefix' => 'api'], function () {
        RouteFacade::get('a', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('b', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('c', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('internal/secret', [ApiPathFilteringTest_Controller::class, 'test']);
    });

    $doc = app(Generator::class)(Scramble::getGeneratorConfig('filtered'));

    expect($doc['servers'][0]['url'])->toBe('http://localhost/api')
        ->and(array_keys($doc['paths']))->toBe(['/a', '/b', '/c']);
});

it('uses root server and full paths for multiple includes', function () {
    Scramble::ignoreDefaultRoutes();

    Scramble::registerApi('multi-base', [
        'api_path' => [
            'include' => ['api', 'second-api'],
        ],
    ]);

    RouteFacade::group(['prefix' => 'api'], function () {
        RouteFacade::get('a', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('b', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('c', [ApiPathFilteringTest_Controller::class, 'test']);
    });

    RouteFacade::group(['prefix' => 'second-api'], function () {
        RouteFacade::get('a', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('b', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('c', [ApiPathFilteringTest_Controller::class, 'test']);
    });

    $doc = app(Generator::class)(Scramble::getGeneratorConfig('multi-base'));

    expect($doc['servers'][0]['url'])->toBe('http://localhost')
        ->and(array_keys($doc['paths']))->toBe([
            '/api/a', '/api/b', '/api/c', '/second-api/a', '/second-api/b', '/second-api/c',
        ]);
});

it('filters routes with wildcard include and exclude patterns', function () {
    Scramble::ignoreDefaultRoutes();

    Scramble::registerApi('wildcard-filtered', [
        'api_path' => [
            'include' => 'api/v*',
            'exclude' => ['api/v1/internal/*'],
        ],
    ]);

    RouteFacade::group(['prefix' => 'api/v1'], function () {
        RouteFacade::get('a', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('internal/secret', [ApiPathFilteringTest_Controller::class, 'test']);
    });

    RouteFacade::group(['prefix' => 'api/v2'], function () {
        RouteFacade::get('a', [ApiPathFilteringTest_Controller::class, 'test']);
        RouteFacade::get('internal/secret', [ApiPathFilteringTest_Controller::class, 'test']);
    });

    RouteFacade::get('api/legacy/a', [ApiPathFilteringTest_Controller::class, 'test']);

    $doc = app(Generator::class)(Scramble::getGeneratorConfig('wildcard-filtered'));

    expect($doc['servers'][0]['url'])->toBe('http://localhost')
        ->and(array_keys($doc['paths']))->toBe([
            '/api/v1/a',
            '/api/v2/a',
            '/api/v2/internal/secret',
        ]);
});

class ApiPathFilteringTest_Controller
{
    public function test() {}
}

<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Support\Facades\Route;

it('attaches operation ID to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', AController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('do_something_magic');
});

it('attaches title to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', BController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Test Endpoint Title');
});

it('attaches description to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', CController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('This is a test endpoint description');
});

it('attaches title and description together to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', DController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Combined Test Title')
        ->and($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('Combined test description');
});

it('attaches all endpoint attributes together', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', EController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('complete_test')
        ->and($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Complete Test')
        ->and($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('Complete test with all attributes');
});

it('uses the method provided in endpoint', function () {
    $openApiDocument = generateForRoute(Route::addRoute(
        ['PUT', 'PATCH'],
        'test',
        #[Endpoint(method: 'PATCH')]
        function () {}
    ));

    expect($openApiDocument['paths']['/test'])->toHaveKeys(['patch']);
});

class AController_EndpointTest
{
    #[Endpoint(operationId: 'do_something_magic')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class BController_EndpointTest
{
    #[Endpoint(title: 'Test Endpoint Title')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class CController_EndpointTest
{
    #[Endpoint(description: 'This is a test endpoint description')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class DController_EndpointTest
{
    #[Endpoint(title: 'Combined Test Title', description: 'Combined test description')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class EController_EndpointTest
{
    #[Endpoint(
        operationId: 'complete_test',
        title: 'Complete Test',
        description: 'Complete test with all attributes'
    )]
    public function __invoke()
    {
        return something_unknown();
    }
}

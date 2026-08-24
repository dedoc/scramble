<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\Route;

it('ignores response by exact status code on controller method', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', ExactStatusController_IgnoreResponseTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->not->toHaveKey(404);
});

it('ignores response by string status code', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', StringStatusController_IgnoreResponseTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->not->toHaveKey(404);
});

it('ignores responses matching status mask', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', StatusMaskController_IgnoreResponseTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->toHaveKey(404)
        ->not->toHaveKey(301)
        ->not->toHaveKey(302);
});

it('supports multiple IgnoreResponse attributes', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', MultipleIgnoreController_IgnoreResponseTest::class));

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->not->toHaveKey(301)
        ->not->toHaveKey(404);
});

it('ignores response on closure route', function () {
    $openApiDocument = generateForRoute(fn () => Route::get(
        'test',
        #[IgnoreResponse(404)]
        function () {
            abort_if(rand(0, 1) > 0, 404);

            return ['ok' => true];
        }
    ));

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->not->toHaveKey(404);
});

class ExactStatusController_IgnoreResponseTest
{
    #[IgnoreResponse(404)]
    public function __invoke()
    {
        abort_if(rand(0, 1) > 0, 404);

        return ['ok' => true];
    }
}

class StringStatusController_IgnoreResponseTest
{
    #[IgnoreResponse('404')]
    public function __invoke()
    {
        abort_if(rand(0, 1) > 0, 404);

        return ['ok' => true];
    }
}

class StatusMaskController_IgnoreResponseTest
{
    #[Response(301)]
    #[Response(302)]
    #[Response(404)]
    #[IgnoreResponse('30*')]
    public function __invoke()
    {
        return ['ok' => true];
    }
}

class MultipleIgnoreController_IgnoreResponseTest
{
    #[Response(301)]
    #[Response(404)]
    #[IgnoreResponse(301)]
    #[IgnoreResponse(404)]
    public function __invoke()
    {
        return ['ok' => true];
    }
}

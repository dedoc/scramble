<?php

use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\Example;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

it('adds headers to 200 response documentation', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'ok']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('200')
        ->and($responses['200'])
        ->toHaveKey('headers')
        ->and($responses['200']['headers'])
        ->toHaveKey('X-Rate-Limit')
        ->and($responses['200']['headers']['X-Rate-Limit'])
        ->toHaveKey('description')
        ->and($responses['200']['headers']['X-Rate-Limit']['description'])
        ->toBe('Rate limiting information');
});

it('adds headers with examples to 200 response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'ok']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Correlation-ID'])
        ->toHaveKey('example')
        ->and($responses['200']['headers']['X-Correlation-ID']['example'])
        ->toBe('123e4567-e89b-12d3-a456-426614174000');
});

it('adds headers for 201 status code', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'created']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('201')
        ->and($responses['201'])
        ->toHaveKey('headers')
        ->and($responses['201']['headers'])
        ->toHaveKey('X-Created-At')
        ->and($responses['201']['headers']['X-Created-At']['description'])
        ->toBe('Creation timestamp');
});

it('adds multiple headers for the same status code (200)', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'ok']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers'])
        ->toHaveKey('X-Rate-Limit')
        ->and($responses['200']['headers'])
        ->toHaveKey('X-Correlation-ID')
        ->and($responses['200']['headers'])
        ->toHaveKey('X-API-Version');
});

it('adds headers with multiple examples to 200 response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'ok']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-API-Version'])
        ->toHaveKey('examples')
        ->and($responses['200']['headers']['X-API-Version']['examples'])
        ->toHaveKey('v1')
        ->and($responses['200']['headers']['X-API-Version']['examples'])
        ->toHaveKey('v2');
});

it('applies wildcard headers to all responses', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withWildcardHeaders']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers'])
        ->toHaveKey('X-Request-ID')
        ->and($responses['200']['headers']['X-Request-ID']['description'])
        ->toBe('Request ID for tracing');
});

it('applies default headers to first success response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withDefaultHeaders']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['201']['headers'])
        ->toHaveKey('X-Rate-Limit')
        ->and($responses['201']['headers']['X-Rate-Limit']['description'])
        ->toBe('Rate limiting information');
});

it('mixes different header types correctly', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'mixedHeaders']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers'])
        ->toHaveKey('X-Request-ID');

    expect($responses['200']['headers'])
        ->toHaveKey('X-Rate-Limit');

    expect($responses['200']['headers'])
        ->not->toHaveKey('X-Error-Code');
});

it('applies wildcard headers to all responses when multiple responses exist', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'multipleResponses']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)
        ->toHaveKey('200')
        ->and($responses)
        ->toHaveKey('404');

    expect($responses['200']['headers'])
        ->toHaveKey('X-Request-ID')
        ->and($responses['404']['headers'])
        ->toHaveKey('X-Request-ID');

    expect($responses['200']['headers'])
        ->toHaveKey('X-Rate-Limit')
        ->and($responses['404']['headers'])
        ->not->toHaveKey('X-Rate-Limit');

    expect($responses['404']['headers'])
        ->toHaveKey('X-Error-Code')
        ->and($responses['200']['headers'])
        ->not->toHaveKey('X-Error-Code');
});

class ResponseHeadersTestController
{
    #[Header('X-Rate-Limit', 'Rate limiting information')]
    #[Header('X-Correlation-ID', 'Correlation ID for tracing', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[Header(
        'X-API-Version',
        'API version header',
        examples: [
            'v1' => new Example('v1', 'Version 1'),
            'v2' => new Example('v2', 'Version 2')
        ]
    )]
    public function ok()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Created-At', 'Creation timestamp', statusCode: 201)]
    public function created()
    {
        return new JsonResponse(['data' => 'created'], 201);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', statusCode: '*')]
    public function withWildcardHeaders()
    {
        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Rate-Limit', 'Rate limiting information')]
    public function withDefaultHeaders()
    {
        return new JsonResponse(['data' => 'created'], 201);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', statusCode: '*')]
    #[Header('X-Rate-Limit', 'Rate limiting information')]
    #[Header('X-Error-Code', 'Error code', statusCode: 404)]
    public function mixedHeaders()
    {   
        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', statusCode: '*')]
    #[Header('X-Rate-Limit', 'Rate limiting information')]
    #[Header('X-Error-Code', 'Error code', statusCode: 404)]
    public function multipleResponses()
    {
        if (request()->has('error')) {
            abort(404, 'Not found');
        }
        return new JsonResponse(['data' => 'success'], 200);
    }
}

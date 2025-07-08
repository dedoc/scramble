<?php

use Dedoc\Scramble\Attributes\Example;
use Dedoc\Scramble\Attributes\Header;
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

it('removes unused response references when dereferenced', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withUnusedReferences']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['404']['headers'])
        ->toHaveKey('X-Custom-Header')
        ->and($openApiDocument)
        ->not->toHaveKey('components');
});

it('adds header with type specification', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withType']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Count'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Count']['schema'])
        ->toHaveKey('type')
        ->and($responses['200']['headers']['X-Count']['schema']['type'])
        ->toBe('integer');
});

it('adds header with format specification', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withFormat']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Date'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Date']['schema'])
        ->toHaveKey('format')
        ->and($responses['200']['headers']['X-Date']['schema']['format'])
        ->toBe('date');
});

it('adds header with default value', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withDefault']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Language'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Language']['schema'])
        ->toHaveKey('default')
        ->and($responses['200']['headers']['X-Language']['schema']['default'])
        ->toBe('en');
});

it('adds header with type, format, and default combined', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withTypeFormatAndDefault']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Timestamp'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Timestamp']['schema'])
        ->toHaveKey('type')
        ->and($responses['200']['headers']['X-Timestamp']['schema']['type'])
        ->toBe('string')
        ->and($responses['200']['headers']['X-Timestamp']['schema'])
        ->toHaveKey('format')
        ->and($responses['200']['headers']['X-Timestamp']['schema']['format'])
        ->toBe('date-time')
        ->and($responses['200']['headers']['X-Timestamp']['schema'])
        ->toHaveKey('default')
        ->and($responses['200']['headers']['X-Timestamp']['schema']['default'])
        ->toBe('2024-01-01T00:00:00Z');
});

it('adds header with boolean type', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withBooleanType']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Cache-Enabled'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Cache-Enabled']['schema'])
        ->toHaveKey('type')
        ->and($responses['200']['headers']['X-Cache-Enabled']['schema']['type'])
        ->toBe('boolean');
});

it('adds header with array type', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withArrayType']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Allowed-Origins'])
        ->toHaveKey('schema')
        ->and($responses['200']['headers']['X-Allowed-Origins']['schema'])
        ->toHaveKey('type')
        ->and($responses['200']['headers']['X-Allowed-Origins']['schema']['type'])
        ->toBe('array');
});

it('adds header with required specification', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withRequired']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Authorization'])
        ->toHaveKey('required')
        ->and($responses['200']['headers']['X-Authorization']['required'])
        ->toBe(true);
});

it('adds header with deprecated specification', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withDeprecated']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Legacy-Header'])
        ->toHaveKey('deprecated')
        ->and($responses['200']['headers']['X-Legacy-Header']['deprecated'])
        ->toBe(true);
});

it('adds header with explode specification', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ResponseHeadersTestController::class, 'withExplode']));

    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses['200']['headers']['X-Tags'])
        ->toHaveKey('explode')
        ->and($responses['200']['headers']['X-Tags']['explode'])
        ->toBe(true);
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
            'v2' => new Example('v2', 'Version 2'),
        ]
    )]
    public function ok()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Created-At', 'Creation timestamp', status: 201)]
    public function created()
    {
        return new JsonResponse(['data' => 'created'], 201);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', status: '*')]
    public function withWildcardHeaders()
    {
        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', status: '*')]
    #[Header('X-Rate-Limit', 'Rate limiting information')]
    #[Header('X-Error-Code', 'Error code', status: 404)]
    public function mixedHeaders()
    {
        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Request-ID', 'Request ID for tracing', status: '*')]
    #[Header('X-Rate-Limit', 'Rate limiting information')]
    #[Header('X-Error-Code', 'Error code', status: 404)]
    public function multipleResponses()
    {
        if (request()->has('error')) {
            abort(404, 'Not found');
        }

        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Custom-Header', 'Custom header description', status: 404)]
    public function withUnusedReferences()
    {
        if (request()->has('not_found')) {
            abort(404, 'Resource not found');
        }

        return new JsonResponse(['data' => 'success'], 200);
    }

    #[Header('X-Count', 'Count header', type: 'int')]
    public function withType()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Date', format: 'date')]
    public function withFormat()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Language', 'Language header', default: 'en')]
    public function withDefault()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Timestamp', 'Timestamp header', type: 'string', format: 'date-time', default: '2024-01-01T00:00:00Z')]
    public function withTypeFormatAndDefault()
    {
        return new JsonResponse(['data' => '2024-01-01T00:00:00Z'], 200);
    }

    #[Header('X-Cache-Enabled', 'Cache enabled header', type: 'bool')]
    public function withBooleanType()
    {
        return new JsonResponse(['data' => true], 200);
    }

    #[Header('X-Allowed-Origins', 'Allowed origins header', type: 'array')]
    public function withArrayType()
    {
        return new JsonResponse(['data' => ['http://example.com']], 200);
    }

    #[Header('X-Authorization', 'Authorization header', required: true)]
    public function withRequired()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Legacy-Header', 'Legacy header', deprecated: true)]
    public function withDeprecated()
    {
        return new JsonResponse(['data' => 'test'], 200);
    }

    #[Header('X-Tags', 'Tags header', explode: true)]
    public function withExplode()
    {
        return new JsonResponse(['data' => ['tag1', 'tag2']], 200);
    }
}

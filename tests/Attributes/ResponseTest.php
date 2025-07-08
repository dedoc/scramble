<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\Route;

it('generates response for basic case', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', AController_ResponseTest::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveCount(1)
        ->and($responses[200]['description'])
        ->toBe('Nice response')
        ->and($responses[200]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string']],
            'required' => ['foo'],
        ]);
});
class AController_ResponseTest
{
    #[Response(200, 'Nice response', type: 'array{"foo": string}')]
    public function __invoke()
    {
        return something_unknown();
    }
}

it('allows adding additional media types response without overriding the original type', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', MultipleTypeController_ResponseTest::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveCount(1)
        ->and($responses[200]['description'])
        ->toBe('When `download` set to `false`, returns the JSON response; when `true`, returns the excel')
        ->and($responses[200]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string']],
            'required' => ['foo'],
        ])
        ->and($responses[200]['content']['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']['schema'])
        ->toBe([
            'type' => 'string',
            'format' => 'binary',
        ]);
});
class MultipleTypeController_ResponseTest
{
    #[Response(200, 'When `download` set to `false`, returns the JSON response; when `true`, returns the excel')]
    #[Response(200, mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', type: 'string', format: 'binary')]
    public function __invoke()
    {
        /**
         * @body array{"foo":string}
         */
        return something_unknown();
    }
}

it('allows adding new response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', NewResponseController_ResponseTest::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveCount(2)
        ->and($responses)
        ->toHaveKeys([200, 201])
        ->and($responses[201]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => ['foo' => ['type' => 'string']],
            'required' => ['foo'],
        ]);
});
class NewResponseController_ResponseTest
{
    #[Response(201, type: 'array{foo: string}')]
    public function __invoke()
    {
        return something_unknown();
    }
}

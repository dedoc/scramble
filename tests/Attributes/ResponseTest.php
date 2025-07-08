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

<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route as RouteFacade;

it('extracts response from `@response` tag', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Foo_ResponseExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties.foo.type', 'string')
        ->toHaveKey('properties.foo.enum', ['bar']);
});
class Foo_ResponseExtensionTest_Controller
{
    /**
     * @response array{"foo": "bar"}
     */
    public function foo()
    {
        return 42;
    }
}

it('ignores annotation when return node is manually annotated', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', [Foo_ResponseExtensionAnnotationTest__Controller::class, 'foo']));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties.foo.type', 'string');
});
class Foo_ResponseExtensionAnnotationTest__Controller
{
    public function foo(): int
    {
        /**
         * @body array{"foo": "bar"}
         */
        return unknown();
    }
}

it('combines responses with different content types', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', MultipleMimes_ResponseExtensionTest_Controller::class));

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][200])
        ->not->toBeNull()
        ->and($response['headers'])->toHaveKey('Content-Disposition')
        ->and($response['content'])->toBe([
            'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'foo' => ['type' => 'string', 'const' => 'bar'],
                    ],
                    'required' => ['foo'],
                ],
            ],
        ]);
});
class MultipleMimes_ResponseExtensionTest_Controller
{
    public function __invoke()
    {
        if (foobar()) {
            return ['foo' => 'bar'];
        }

        return response()->download('data.pdf');
    }
}

it('documents responses with union type hint', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', UnionTypeHint_ResponseExtensionTest_Controller::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKeys([200, 419])
        ->and($responses[200]['content']['application/json']['schema']['type'])->toBe('object')
        ->and($responses[419]['content']['application/json']['schema']['type'])->toBe('array');
});
class UnionTypeHint_ResponseExtensionTest_Controller
{
    public function __invoke(): Resource_ResponseExtensionTest|JsonResponse
    {
        if (foobar()) {
            return new Resource_ResponseExtensionTest;
        }

        return response()->json([], 419);
    }
}
class Resource_ResponseExtensionTest extends JsonResource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        return ['id' => 42];
    }
}

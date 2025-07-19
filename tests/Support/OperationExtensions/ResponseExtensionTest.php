<?php

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
                        'foo' => ['type' => 'string', 'enum' => ['bar']],
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

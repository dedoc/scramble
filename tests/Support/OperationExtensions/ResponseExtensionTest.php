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

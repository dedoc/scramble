<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Example;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Parameter;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

it('body parameters attaches info to inferred params', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->post('api/test', BodyParameterController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the company',
                ],
            ],
            'required' => ['name'],
        ]);
});
class BodyParameterController_ParameterAnnotationsTest
{
    #[BodyParameter('name', 'The name of the company')]
    public function __invoke(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
        ]);
    }
}

it('retrieves parameters from Parameter annotations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', ParameterController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'per_page',
            'in' => 'query',
            'schema' => [
                'type' => 'integer',
                'default' => 15,
            ],
        ]);
});
class ParameterController_ParameterAnnotationsTest
{
    #[Parameter('query', 'per_page', type: 'int', default: 15)]
    public function __invoke() {}
}

it('supports path parameters attributes', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test/{testId}', ParameterController_PathParameterTest::class));

    expect($openApi['paths']['/test/{testId}']['get']['parameters'][0])
        ->toBe([
            'name' => 'testId',
            'in' => 'path',
            'required' => true,
            'description' => 'Nice test ID',
            'schema' => [
                'type' => 'string',
            ],
        ]);
});
class ParameterController_PathParameterTest
{
    #[PathParameter('testId', 'Nice test ID')]
    public function __invoke(string $testId) {}
}

it('supports simple example for Parameter annotations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', ParameterSimpleExampleController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'per_page',
            'in' => 'query',
            'schema' => [
                'type' => 'integer',
                'default' => 15,
            ],
            'example' => 10,
        ]);
});
class ParameterSimpleExampleController_ParameterAnnotationsTest
{
    #[Parameter('query', 'per_page', type: 'int', default: 15, example: 10)]
    public function __invoke() {}
}

it('allows annotating parameters with the same names', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', SameNameParametersController_ParameterAnnotationsTest::class));

    expect($parameters = $openApi['paths']['/test']['get']['parameters'])
        ->toHaveCount(2)
        ->and($parameters[0]['name'])->toBe('per_page')
        ->and($parameters[1]['name'])->toBe('per_page')
        ->and($parameters[0]['in'])->toBe('query')
        ->and($parameters[1]['in'])->toBe('header');
});
class SameNameParametersController_ParameterAnnotationsTest
{
    #[QueryParameter('per_page')]
    #[HeaderParameter('per_page')]
    public function __invoke() {}
}

it('allows defining parameters with the same names as inferred in different locations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test/{test}', SameNameParametersAsInferredController_ParameterAnnotationsTest::class));

    expect($parameters = $openApi['paths']['/test/{test}']['get']['parameters'])
        ->toHaveCount(2)
        ->and($parameters[0]['name'])->toBe('test')
        ->and($parameters[1]['name'])->toBe('test')
        ->and($parameters[0]['in'])->toBe('path')
        ->and($parameters[1]['in'])->toBe('query');
});
class SameNameParametersAsInferredController_ParameterAnnotationsTest
{
    #[QueryParameter('test')]
    public function __invoke(string $test) {}
}

it('supports complex examples for Parameter annotations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', ParameterComplexExampleController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'per_page',
            'in' => 'query',
            'schema' => [
                'type' => 'integer',
                'default' => 15,
            ],
            'examples' => [
                'max' => [
                    'value' => 99,
                    'summary' => 'Max amount of stuff',
                    'description' => 'Really big item',
                ],
            ],
        ]);
});
class ParameterComplexExampleController_ParameterAnnotationsTest
{
    #[Parameter('query', 'per_page', type: 'int', default: 15, examples: ['max' => new Example(99, 'Max amount of stuff', 'Really big item')])]
    public function __invoke() {}
}

it('merges parameter data with the data inferred from Parameter annotations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', ParameterOverridingController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'per_page',
            'in' => 'query',
            'schema' => [
                'type' => 'integer',
                'default' => 15,
            ],
        ]);
});
class ParameterOverridingController_ParameterAnnotationsTest
{
    #[Parameter('query', 'per_page', default: 15)]
    public function __invoke(Request $request)
    {
        $request->validate(['per_page' => 'int']);
    }
}

it('supports subclass Parameter annotations', function () {
    $openApi = generateForRoute(fn (Router $r) => $r->get('api/test', QueryParameterController_ParameterAnnotationsTest::class));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'per_page',
            'in' => 'header',
            'schema' => [
                'type' => 'integer',
                'default' => 15,
            ],
        ]);
});
class QueryParameterController_ParameterAnnotationsTest
{
    #[HeaderParameter('per_page', type: 'int', default: 15)]
    public function __invoke() {}
}

it('supports subclass annotations on closure routes', function () {
    $openApi = generateForRoute(Route::get(
        'api/test',
        #[HeaderParameter('X-Retry-After', type: 'int')]
        function () {}
    ));

    expect($openApi['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'X-Retry-After',
            'in' => 'header',
            'schema' => [
                'type' => 'integer',
            ],
        ]);
});

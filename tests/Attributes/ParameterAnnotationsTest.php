<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Example;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Parameter;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

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

//  body parameters test, pay attention to required property!

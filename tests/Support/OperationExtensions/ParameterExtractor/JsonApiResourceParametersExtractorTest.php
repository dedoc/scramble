<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Route as RouteFacade;

test('extracts includes parameter', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('/api/test', function () {
        return SamplePostResource_JsonApiResourceParametersExtractorTest::make();
    }));

    $parameters = collect($openApiDocument['paths']['/test']['get']['parameters']);

    expect($parameters->firstWhere('name', 'include'))->toBe([
        'name' => 'include',
        'in' => 'query',
        'schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => [
                    'user',
                    'parent',
                ]
            ]
        ],
        'explode' => false,
    ]);
});

test('extracts fields parameter', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('/api/test', function () {
        return SamplePostResource_JsonApiResourceParametersExtractorTest::make();
    }));

    $parameters = collect($openApiDocument['paths']['/test']['get']['parameters']);

    expect($parameters->firstWhere('name', 'fields[sample_post_resource__json_apis]'))->toBe([
        'name' => 'fields[sample_post_resource__json_apis]',
        'in' => 'query',
        'schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => [
                    'email',
                ]
            ]
        ],
        'explode' => false,
    ]);
});
/**
 * @property SamplePostModel $resource
 */
class SamplePostResource_JsonApiResourceParametersExtractorTest extends JsonApiResource
{
    public $attributes = ['email'];

    public $relationships = ['user', 'parent'];
}

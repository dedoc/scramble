<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
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
        'description' => 'Available includes are `user`, `parent`. You can include multiple options by separating them with a comma.',
        'schema' => [
            'type' => 'string',
        ],
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
        'description' => 'Available fields are `email`. You can include multiple options by separating them with a comma.',
        'schema' => [
            'type' => 'string',
        ],
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

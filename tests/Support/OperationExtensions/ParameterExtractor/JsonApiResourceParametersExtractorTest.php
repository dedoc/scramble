<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Tests\Files\SampleCircleModel;
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
        'schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => [
                    'user',
                    'parent',
                ],
            ],
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
                ],
            ],
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

test('extracts deep includes enum and fields for all resources', function () {
    JsonApiResource::$maxRelationshipDepth = 5;

    $openApiDocument = generateForRoute(RouteFacade::get('/api/test', function () {
        return DeepPostResource_JsonApiResourceParametersExtractorTest::make();
    }));

    $parameters = collect($openApiDocument['paths']['/test']['get']['parameters']);

    expect($parameters->firstWhere('name', 'include')['schema']['items']['enum'])->toBe([
        'user',
        'user.circles',
        'user.circles.user',
        'user.circles.user.circles',
        'user.circles.user.circles.user',
    ]);

    expect($parameters->firstWhere('name', 'fields[deep_post_resource__json_apis]')['schema']['items']['enum'])->toBe(['title']);
    expect($parameters->firstWhere('name', 'fields[deep_user_resource__json_apis]')['schema']['items']['enum'])->toBe(['name']);
    expect($parameters->firstWhere('name', 'fields[deep_circle_resource__json_apis]')['schema']['items']['enum'])->toBe(['some_attr']);
});
/**
 * @property SamplePostModel $resource
 */
class DeepPostResource_JsonApiResourceParametersExtractorTest extends JsonApiResource
{
    public $attributes = ['title'];

    public $relationships = [
        'user' => DeepUserResource_JsonApiResourceParametersExtractorTest::class,
    ];
}
/**
 * @property SampleUserModel $resource
 */
class DeepUserResource_JsonApiResourceParametersExtractorTest extends JsonApiResource
{
    public $attributes = ['name'];

    public $relationships = [
        'circles' => DeepCircleResource_JsonApiResourceParametersExtractorTest::class,
    ];
}
/**
 * @property SampleCircleModel $resource
 */
class DeepCircleResource_JsonApiResourceParametersExtractorTest extends JsonApiResource
{
    public $attributes = ['some_attr'];

    public $relationships = [
        'user' => DeepUserResource_JsonApiResourceParametersExtractorTest::class,
    ];
}

/*
 * Based on method name, `ignoreFieldsAndIncludesInQueryString`, this should also ignore includes. But as of now (Laravel 13.3),
 * `include` parameter is still available. Scramble reflects that.
 */
test('ignores fields parameter and preserves includes parameter when ignoreFieldsAndIncludesInQueryString is called', function () {
    $openApiDocument = generateForRoute(RouteFacade::get('/api/test', function () {
        return SamplePostResource_JsonApiResourceParametersExtractorTest::make()->ignoreFieldsAndIncludesInQueryString();
    }));

    $parameters = collect($openApiDocument['paths']['/test']['get']['parameters']);

    expect($parameters->firstWhere('name', 'include'))
        ->not->toBe(null)
        ->and($parameters->firstWhere('name', 'fields[sample_post_resource__json_apis]'))
        ->toBe(null);
});

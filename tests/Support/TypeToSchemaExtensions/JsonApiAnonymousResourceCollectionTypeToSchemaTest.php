<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

test('json api collection response has correct content type', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [JsonApiCollectionResponse_Controller::class, 'index']));

    $response = $openApiDocument['paths']['/test']['get']['responses']['200'];

    expect($response)->toHaveKey('content');
    expect($response['content'])->toHaveKey('application/vnd.api+json');
    expect($response['content'])->not->toHaveKey('application/json');
});

test('json api collection response includes included key when resource has relationships', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [JsonApiCollectionWithRelationships_Controller::class, 'index']));

    $schema = $openApiDocument['paths']['/test']['get']['responses']['200']['content']['application/vnd.api+json']['schema'];

    expect($schema['properties'])->toHaveKey('data');
    expect($schema['properties'])->toHaveKey('included');
    expect($schema['properties']['included']['type'])->toBe('array');
});

test('json api collection response has no included key when resource has no relationships', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [JsonApiCollectionResponse_Controller::class, 'index']));

    $schema = $openApiDocument['paths']['/test']['get']['responses']['200']['content']['application/vnd.api+json']['schema'];

    expect($schema['properties'])->not->toHaveKey('included');
});

class JsonApiCollectionResponse_Controller
{
    public function index()
    {
        return JsonApiCollectionResponse_Resource::collection([]);
    }
}

/**
 * @property-read SampleUserModel $resource
 */
class JsonApiCollectionResponse_Resource extends JsonApiResource
{
    public $attributes = ['name', 'email'];
}

class JsonApiCollectionWithRelationships_Controller
{
    public function index()
    {
        return JsonApiCollectionWithRelationships_PostResource::collection([]);
    }
}

/**
 * @property-read SamplePostModel $resource
 */
class JsonApiCollectionWithRelationships_PostResource extends JsonApiResource
{
    public $attributes = ['title'];

    public function toRelationships(Request $request): array
    {
        return [
            'user' => JsonApiCollectionWithRelationships_UserResource::class,
        ];
    }
}

/**
 * @property-read SampleUserModel $resource
 */
class JsonApiCollectionWithRelationships_UserResource extends JsonApiResource
{
    public $attributes = ['name', 'email'];
}

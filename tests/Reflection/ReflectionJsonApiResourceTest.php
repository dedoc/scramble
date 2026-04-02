<?php

namespace Dedoc\Scramble\Tests\Reflection;

use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

test('returns attributes type from property', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestProperty_JsonApi::class);

    expect($resource->getAttributesType()->toString())->toBe('array{name: string, email: string, created_at: Carbon\Carbon|null}');
});
/**
 * @property-read SampleUserModel $resource
 */
class ReflectionJsonApiResourceTestProperty_JsonApi extends JsonApiResource
{
    public $attributes = [
        'name',
        'email',
        'created_at',
    ];
}

test('returns attributes type from toAttributes method', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestToAttributes_JsonApi::class);

    expect($resource->getAttributesType()->toString())->toBe('array{name: string, email: string, is_gmail: (): boolean, created_at: Carbon\Carbon|null}');
});
/**
 * @property-read SampleUserModel $resource
 */
class ReflectionJsonApiResourceTestToAttributes_JsonApi extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'is_gmail' => fn () => mt_rand(0, 1) === 0,
            'created_at' => $this->created_at,
        ];
    }
}

test('returns attributes type from both property and method correctly', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestToAttributesAndProperty_JsonApi::class);

    expect($resource->getAttributesType()->toString())->toBe('array{is_gmail: (): boolean}');
});
/**
 * @property-read SampleUserModel $resource
 */
class ReflectionJsonApiResourceTestToAttributesAndProperty_JsonApi extends JsonApiResource
{
    public $attributes = [
        'name',
        'email',
        'created_at',
    ];

    public function toAttributes(Request $request): array
    {
        return [
            'is_gmail' => fn () => mt_rand(0, 1) === 0,
        ];
    }
}

test('returns relationships type from property without guessed class', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestProperty_RelationshipsJsonApi::class);

    expect($resource->getRelationshipsType()->toString())->toBe('array{user: Illuminate\Http\Resources\JsonApi\JsonApiResource}');
});
/**
 * @property-read SamplePostModel $resource
 */
class ReflectionJsonApiResourceTestProperty_RelationshipsJsonApi extends JsonApiResource
{
    public $relationships = [
        'user',
    ];
}

test('returns relationships type from property with concrete class', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestProperty_RelationshipsConcreteJsonApi::class);

    expect($resource->getRelationshipsType()->toString())->toBe('array{user: '.ReflectionJsonApiResourceTestProperty_JsonApi::class.'}');
});
/**
 * @property-read SamplePostModel $resource
 */
class ReflectionJsonApiResourceTestProperty_RelationshipsConcreteJsonApi extends JsonApiResource
{
    public $relationships = [
        'user' => ReflectionJsonApiResourceTestProperty_JsonApi::class,
    ];
}

test('returns relationships type from toRelationships method', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTestProperty_ToRelationshipsJsonApi::class);

    expect($resource->getRelationshipsType()->toString())->toBe('array{user: '.ReflectionJsonApiResourceTestProperty_JsonApi::class.', parentPosts: '.AnonymousResourceCollection::class.'<list{}, array<mixed>, '.ReflectionJsonApiResourceTestProperty_RelationshipsConcreteJsonApi::class.'>, parent: '.JsonApiResource::class.'}');
});
/**
 * @property-read SamplePostModel $resource
 */
class ReflectionJsonApiResourceTestProperty_ToRelationshipsJsonApi extends JsonApiResource
{
    public function toRelationships(Request $request)
    {
        return [
            'user' => ReflectionJsonApiResourceTestProperty_JsonApi::class,
            'parentPosts' => fn () => ReflectionJsonApiResourceTestProperty_RelationshipsConcreteJsonApi::collection([]),
            'parent'
        ];
    }
}

test('returns to links type from resource', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTest_ToLinksJsonApi::class);

    expect($resource->getLinksType()->toString())->toBe('array{self: string}');
});
/**
 * @property-read SampleUserModel $resource
 */
class ReflectionJsonApiResourceTest_ToLinksJsonApi extends JsonApiResource
{
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.posts.show', $this->resource),
        ];
    }
}

test('returns to meta type from resource', function () {
    $resource = ReflectionJsonApiResource::createForClass(ReflectionJsonApiResourceTest_ToMetaJsonApi::class);

    expect($resource->getMetaType()->toString())->toBe('array{readable_created_at: unknown}');
});
/**
 * @property-read SampleUserModel $resource
 */
class ReflectionJsonApiResourceTest_ToMetaJsonApi extends JsonApiResource
{
    public function toMeta(Request $request): array
    {
        return [
            'readable_created_at' => $this->created_at->diffForHumans(),
        ];
    }
}

<?php

namespace Dedoc\Scramble\Tests\Reflection;

use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Str;

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

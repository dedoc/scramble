<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Attributes\UseResource;

require_once __DIR__.'/../../Files/JsonResourceHelperTestModels.php';
require_once __DIR__.'/../../Files/JsonResourceHelperTestAppResources.php';
require_once __DIR__.'/../../Files/JsonResourceHelperTestPackageResources.php';

it('resolves a model when its resource convention points back to the resource', function () {
    $type = JsonResourceHelper::modelType(
        app(Infer::class)->analyzeClass(App\Http\Resources\ReverseLookupTest_UserResource::class),
    );

    expect($type)->toBeInstanceOf(ObjectType::class)
        ->and($type->name)->toBe(App\Models\ReverseLookupTest_User::class);
});

it('does not resolve an unrelated application model for a package resource', function () {
    $type = JsonResourceHelper::modelType(
        app(Infer::class)->analyzeClass(Vendor\Package\Http\Resources\ReverseLookupTest_UserResource::class),
    );

    expect($type)->toBeInstanceOf(UnknownType::class);
});

it('resolves a model with an explicitly configured resource', function () {
    $type = JsonResourceHelper::modelType(
        app(Infer::class)->analyzeClass(Vendor\Package\Http\Resources\ReverseLookupTest_AttributedResource::class),
    );

    expect($type)->toBeInstanceOf(ObjectType::class)
        ->and($type->name)->toBe(App\Models\ReverseLookupTest_Attributed::class);
})->skip(fn () => ! class_exists(UseResource::class));

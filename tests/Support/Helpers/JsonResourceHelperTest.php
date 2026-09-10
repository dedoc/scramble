<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/../../Files/JsonResourceHelperTestModels.php';
require_once __DIR__.'/../../Files/JsonResourceHelperTestAppResources.php';
require_once __DIR__.'/../../Files/JsonResourceHelperTestPackageResources.php';

it('resolves a model using the reversed Laravel resource convention', function (string $resource, string $model) {
    $type = JsonResourceHelper::modelType(
        app(Infer::class)->analyzeClass($resource),
    );

    expect($type)->toBeInstanceOf(ObjectType::class)
        ->and($type->name)->toBe($model);
})->with([
    [App\Http\Resources\ReverseLookupTest_UserResource::class, App\Models\ReverseLookupTest_User::class],
    [App\Http\Resources\ReverseLookupTest_Plain::class, App\Models\ReverseLookupTest_Plain::class],
]);

it('does not resolve an unrelated application model for a package resource', function () {
    $type = JsonResourceHelper::modelType(
        app(Infer::class)->analyzeClass(Vendor\Package\Http\Resources\ReverseLookupTest_UserResource::class),
    );

    expect($type)->toBeInstanceOf(UnknownType::class);
});

it('documents a reflection-only package resource instead of an unrelated application model', function () {
    app(Infer::class)->configure()->buildDefinitionsUsingReflectionFor([
        Vendor\Package\Http\Resources\ReverseLookupTest_UserResource::class,
    ]);

    $openApiDocument = generateForRoute(
        fn () => Route::get('api/test', JsonResourceHelperTest_Controller::class),
    );

    expect(
        $openApiDocument['components']['schemas']['ReverseLookupTest_UserResource']['properties'] ?? null,
    )->toBe([
        'id' => ['type' => 'integer'],
        'roles' => [
            'type' => 'array',
            'prefixItems' => [
                ['type' => 'string', 'const' => 'admin'],
            ],
            'minItems' => 1,
            'maxItems' => 1,
        ],
    ]);
});

class JsonResourceHelperTest_Controller
{
    public function __invoke()
    {
        return new Vendor\Package\Http\Resources\ReverseLookupTest_UserResource(
            new App\Models\ReverseLookupTest_User,
        );
    }
}

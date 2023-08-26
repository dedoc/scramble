<?php

use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Support\Facades\Route as RouteFacade;

it('uses getRouteKeyName to determine model route key type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{model}', [Foo_RequestEssentialsExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test/{model}']['get']['parameters'][0])
        ->toHaveKey('schema.type', 'string')
        ->toHaveKey('description', 'The model name');
});
class ModelWithCustomRouteKeyName extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    public function getRouteKeyName()
    {
        return 'name'; // this is a string column
    }
}
class Foo_RequestEssentialsExtensionTest_Controller
{
    public function foo(ModelWithCustomRouteKeyName $model)
    {
    }
}

it('handles custom key from route to determine model route key type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{user:name}', [CustomKey_RequestEssentialsExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test/{user}']['get']['parameters'][0])
        ->toHaveKey('schema.type', 'string')
        ->toHaveKey('description', 'The user name');
});

it('determines default model route key type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{user}', [CustomKey_RequestEssentialsExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test/{user}']['get']['parameters'][0])
        ->toHaveKey('schema.type', 'integer')
        ->toHaveKey('description', 'The user ID');
});
class CustomKey_RequestEssentialsExtensionTest_Controller
{
    public function foo(SampleUserModel $user)
    {
    }
}

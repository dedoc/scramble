<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('uses getRouteKeyName to determine model route key type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{model}', [Foo_RequestEssentialsExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test/{model}']['get']['parameters'][0]['schema']['type'])
        ->toBe('string');
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
    public function foo(ModelWithCustomRouteKeyName $model) {}
}

<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('documents a response even when return type is taken from an annotation', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ManualResponseDocumentation_Test::class, 'a']);
    });

    expect($openApiDocument['paths']['/test']['get']['responses'][200])
        ->toHaveKey('description', 'Wow.')
        ->toHaveKey('content.application/json.schema.properties.id.type', 'integer');
});

class ManualResponseDocumentation_Test extends \Illuminate\Routing\Controller
{
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        /**
         * Wow.
         *
         * @body array{id: int}
         */
        return $this->unknown_fn();
    }
}

it('doesnt use comments from service classes', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', ServiceResponseDocumentation_Test::class);
    });

    expect($openApiDocument['paths']['/test']['get']['responses'][200])
        ->toHaveKey('description', '');
});

class ServiceResponseDocumentation_Test extends \Illuminate\Routing\Controller
{
    public function __invoke()
    {
        return (new Foo_ManualResponseDocumentationTest)->foo();
    }
}
class Foo_ManualResponseDocumentationTest
{
    public function foo()
    {
        /**
         * Wow.
         */
        return $this->unknown_fn();
    }
}

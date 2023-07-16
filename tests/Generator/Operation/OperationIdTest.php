<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('documents operation id based on controller base name if no route name and not set manually', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [AutomaticOperationIdDocumentationTestController::class, 'a']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('operationId', 'automaticOperationIdDocumentationTest.a');
});
class AutomaticOperationIdDocumentationTestController extends \Illuminate\Routing\Controller
{
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        return $this->unknown_fn();
    }
}

it('documents operation id based on route name if not set manually', function () {
    $openApiDocument = generateForRoute(function () {
        RouteFacade::name('api.')->group(function () use (&$route) {
            $route = RouteFacade::get('api/test', [NamedOperationIdDocumentationTestController::class, 'a'])->name('namedOperationIdA');
        });

        return $route;
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('operationId', 'api.namedOperationIdA');
});
class NamedOperationIdDocumentationTestController extends \Illuminate\Routing\Controller
{
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        return $this->unknown_fn();
    }
}

it('documents operation id based phpdoc param', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ManualOperationIdDocumentationTestController::class, 'a'])->name('someNameOfRoute');
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('operationId', 'manualOperationId');
});
class ManualOperationIdDocumentationTestController extends \Illuminate\Routing\Controller
{
    /**
     * @operationId manualOperationId
     */
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        return $this->unknown_fn();
    }
}

<?php

namespace Dedoc\Scramble\Tests\Generator\Request;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
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
        ->toHaveKey('operationId', 'namedOperationIdA');
});
class NamedOperationIdDocumentationTestController extends \Illuminate\Routing\Controller
{
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        return $this->unknown_fn();
    }
}

it('ensures operation id is unique if not set manually', function () {
    /*
     * This is the setup that can make operationId not unique as name for both routes
     * is `api.`.
     */
    RouteFacade::name('api.')->group(function () use (&$route) {
        RouteFacade::get('api/test/a', [UniqueOperationIdDocumentationTestController::class, 'a']);
        RouteFacade::get('api/test/b', [UniqueOperationIdDocumentationTestController::class, 'b']);
    });
    Scramble::routes(fn (Route $r) => str_contains($r->uri, 'test/'));
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    expect($openApiDocument['paths']['/test/a']['get']['operationId'])
        ->not->toBe($openApiDocument['paths']['/test/b']['get']['operationId']);
});
class UniqueOperationIdDocumentationTestController extends \Illuminate\Routing\Controller
{
    public function a()
    {
        return $this->unknown_fn();
    }

    public function b()
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

it('documents operation id with manual extension', function () {
    Scramble::configure()->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
        $operation->setOperationId('extensionOperationIdDocumentationTest');
    });

    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ExtensionOperationIdDocumentationTestController::class, 'a']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('operationId', 'extensionOperationIdDocumentationTest');
});

class ExtensionOperationIdDocumentationTestController extends Controller
{
    public function a() {}
}

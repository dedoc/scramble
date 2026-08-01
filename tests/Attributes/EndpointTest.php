<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('attaches operation ID to controller action', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('test', AController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('do_something_magic');
});

it('attaches title to controller action', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('test', BController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Test Endpoint Title');
});

it('attaches description to controller action', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('test', CController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('This is a test endpoint description');
});

it('attaches title and description together to controller action', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('test', DController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Combined Test Title')
        ->and($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('Combined test description');
});

it('attaches all endpoint attributes together', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('test', EController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('complete_test')
        ->and($openApiDocument['paths']['/test']['get']['summary'])
        ->toBe('Complete Test')
        ->and($openApiDocument['paths']['/test']['get']['description'])
        ->toBe('Complete test with all attributes');
});

it('uses the method provided in endpoint', function () {
    $openApiDocument = generateForRoute(RouteFacade::addRoute(
        ['PUT', 'PATCH'],
        'test',
        #[Endpoint(method: 'PATCH')]
        function () {}
    ));

    expect($openApiDocument['paths']['/test'])->toHaveKeys(['patch']);
});

it('allows sorting endpoints by weight', function () {
    RouteFacade::get('api/activate', EndpointWeightTest_Activate_Controller::class);
    RouteFacade::get('api/show', EndpointWeightTest_Show_Controller::class);
    RouteFacade::get('api/list', EndpointWeightTest_List_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/activate', 'api/show', 'api/list']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/list', '/show', '/activate']);
});

it('preserves registration order when endpoint weight is omitted', function () {
    RouteFacade::get('api/activate', EndpointWeightTest_NoWeight_Activate_Controller::class);
    RouteFacade::get('api/show', EndpointWeightTest_NoWeight_Show_Controller::class);
    RouteFacade::get('api/list', EndpointWeightTest_NoWeight_List_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/activate', 'api/show', 'api/list']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/activate', '/show', '/list']);
});

it('preserves registration order when endpoint uses default weight', function () {
    RouteFacade::get('api/a', EndpointWeightTest_DefaultWeight_A_Controller::class);
    RouteFacade::get('api/b', EndpointWeightTest_DefaultWeight_B_Controller::class);
    RouteFacade::get('api/c', EndpointWeightTest_DefaultWeight_C_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b', 'api/c']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/a', '/b', '/c']);
});

it('sorts by endpoint weight within the same group', function () {
    RouteFacade::get('api/activate', EndpointWeightTest_Grouped_Activate_Controller::class);
    RouteFacade::get('api/show', EndpointWeightTest_Grouped_Show_Controller::class);
    RouteFacade::get('api/list', EndpointWeightTest_Grouped_List_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/activate', 'api/show', 'api/list']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/list', '/show', '/activate']);
});

it('keeps group weight as the primary sort key over endpoint weight', function () {
    RouteFacade::get('api/a', EndpointWeightTest_GroupPrimary_A_Controller::class);
    RouteFacade::get('api/b', EndpointWeightTest_GroupPrimary_B_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/b', '/a']);
});

class AController_EndpointTest
{
    #[Endpoint(operationId: 'do_something_magic')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class BController_EndpointTest
{
    #[Endpoint(title: 'Test Endpoint Title')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class CController_EndpointTest
{
    #[Endpoint(description: 'This is a test endpoint description')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class DController_EndpointTest
{
    #[Endpoint(title: 'Combined Test Title', description: 'Combined test description')]
    public function __invoke()
    {
        return something_unknown();
    }
}

class EController_EndpointTest
{
    #[Endpoint(
        operationId: 'complete_test',
        title: 'Complete Test',
        description: 'Complete test with all attributes'
    )]
    public function __invoke()
    {
        return something_unknown();
    }
}

class EndpointWeightTest_Activate_Controller
{
    #[Endpoint(weight: 2)]
    public function __invoke() {}
}

class EndpointWeightTest_Show_Controller
{
    #[Endpoint(weight: 1)]
    public function __invoke() {}
}

class EndpointWeightTest_List_Controller
{
    #[Endpoint(weight: 0)]
    public function __invoke() {}
}

class EndpointWeightTest_NoWeight_Activate_Controller
{
    #[Group(name: 'Users')]
    public function __invoke() {}
}

class EndpointWeightTest_NoWeight_Show_Controller
{
    #[Group(name: 'Users')]
    public function __invoke() {}
}

class EndpointWeightTest_NoWeight_List_Controller
{
    #[Group(name: 'Users')]
    public function __invoke() {}
}

class EndpointWeightTest_DefaultWeight_A_Controller
{
    #[Group(name: 'Users')]
    #[Endpoint(title: 'A')]
    public function __invoke() {}
}

class EndpointWeightTest_DefaultWeight_B_Controller
{
    #[Group(name: 'Users')]
    public function __invoke() {}
}

class EndpointWeightTest_DefaultWeight_C_Controller
{
    #[Group(name: 'Users')]
    #[Endpoint(title: 'C')]
    public function __invoke() {}
}

#[Group(name: 'Users', weight: 0)]
class EndpointWeightTest_Grouped_Activate_Controller
{
    #[Endpoint(weight: 2)]
    public function __invoke() {}
}

#[Group(name: 'Users', weight: 0)]
class EndpointWeightTest_Grouped_Show_Controller
{
    #[Endpoint(weight: 1)]
    public function __invoke() {}
}

#[Group(name: 'Users', weight: 0)]
class EndpointWeightTest_Grouped_List_Controller
{
    #[Endpoint(weight: 0)]
    public function __invoke() {}
}

#[Group(name: 'Later', weight: 1)]
class EndpointWeightTest_GroupPrimary_A_Controller
{
    #[Endpoint(weight: 0)]
    public function __invoke() {}
}

#[Group(name: 'Earlier', weight: 0)]
class EndpointWeightTest_GroupPrimary_B_Controller
{
    #[Endpoint(weight: 10)]
    public function __invoke() {}
}

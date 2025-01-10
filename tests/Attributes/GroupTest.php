<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('allows sorting groups', function () {
    RouteFacade::get('api/a', GroupTest_A_Controller::class);
    RouteFacade::get('api/b', GroupTest_B_Controller::class);
    RouteFacade::get('api/c', GroupTest_C_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b', 'api/c']));

    $openApiDoc = app()->make(\Dedoc\Scramble\Generator::class)();

    expect(array_keys($openApiDoc['paths']))->toBe(['/c', '/b', '/a']);
});
#[Group(weight: 2)]
class GroupTest_A_Controller
{
    public function __invoke() {}
}
#[Group(weight: 1)]
class GroupTest_B_Controller
{
    public function __invoke() {}
}
#[Group(weight: 0)]
class GroupTest_C_Controller
{
    public function __invoke() {}
}

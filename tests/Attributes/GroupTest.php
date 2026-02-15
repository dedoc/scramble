<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('allows sorting groups without document level tags', function () {
    RouteFacade::get('api/a', GroupTest_A_Controller::class);
    RouteFacade::get('api/b', GroupTest_B_Controller::class);
    RouteFacade::get('api/c', GroupTest_C_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b', 'api/c']));

    $openApiDoc = app()->make(Generator::class)();

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

it('allows groups defined on route methods', function () {
    RouteFacade::get('api/a', GroupTest_A2_Controller::class);
    RouteFacade::get('api/b', GroupTest_B2_Controller::class);
    RouteFacade::get('api/c', GroupTest_C2_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b', 'api/c']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))
        ->toBe(['/c', '/b', '/a'])
        ->and(data_get($openApiDoc['paths'], '*.*.tags.*'))
        ->toBe(['C 2', 'B 2', 'A 2']);
});
class GroupTest_A2_Controller
{
    #[Group(name: 'A 2', weight: 2)]
    public function __invoke() {}
}
class GroupTest_B2_Controller
{
    #[Group(name: 'B 2', weight: 1)]
    public function __invoke() {}
}
class GroupTest_C2_Controller
{
    #[Group(name: 'C 2', weight: 0)]
    public function __invoke() {}
}

it('stores named group as the document level tag', function () {
    $openApiDoc = generateForRoute(fn () => RouteFacade::get('api/d', GroupTest_D_Controller::class));

    expect($openApiDoc['tags'])->toBe([[
        'name' => 'D',
        'description' => 'Wow',
    ]]);
});
#[Group(name: 'D', description: 'Wow')]
class GroupTest_D_Controller
{
    public function __invoke() {}
}

it('keeps first most specific named group as the document tag', function () {
    RouteFacade::get('api/e1', GroupTest_E_Controller::class);
    RouteFacade::get('api/e2', GroupTest_E2_Controller::class);
    RouteFacade::get('api/e3', GroupTest_E3_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/e1', 'api/e2', 'api/e3']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['tags'])->toBe([[
        'name' => 'E',
        'description' => 'Specific description',
    ]]);
});
#[Group(name: 'E', description: 'Specific description')]
class GroupTest_E_Controller
{
    public function __invoke() {}
}
#[Group(name: 'E')]
class GroupTest_E2_Controller
{
    public function __invoke() {}
}
#[Group(name: 'E', description: 'Ignored description')]
class GroupTest_E3_Controller
{
    public function __invoke() {}
}

it('allows sorting groups with document level tags', function () {
    RouteFacade::get('api/a', GroupTest_A4_Controller::class);
    RouteFacade::get('api/b', GroupTest_B4_Controller::class);
    RouteFacade::get('api/c', GroupTest_C4_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/a', 'api/b', 'api/c']));

    $openApiDoc = app()->make(Generator::class)();

    expect(array_keys($openApiDoc['paths']))
        ->toBe(['/c', '/b', '/a'])
        ->and($openApiDoc['tags'])
        ->toBe([
            ['name' => 'C'],
            ['name' => 'B'],
            ['name' => 'A'],
        ]);
});
#[Group('A', weight: 2)]
class GroupTest_A4_Controller
{
    public function __invoke() {}
}
#[Group('B', weight: 1)]
class GroupTest_B4_Controller
{
    public function __invoke() {}
}
#[Group('C', weight: 0)]
class GroupTest_C4_Controller
{
    public function __invoke() {}
}

it('generates x-tagGroups for tags with parent parameter', function () {
    RouteFacade::get('api/users', GroupTest_Users_Controller::class);
    RouteFacade::get('api/profiles', GroupTest_Profiles_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/users', 'api/profiles']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc)
        ->toHaveKey('x-tagGroups')
        ->and($openApiDoc['x-tagGroups'])->toBe([[
            'name' => 'User Management',
            'tags' => ['users', 'profiles'],
        ]]);
});
#[Group(name: 'users', parent: 'User Management', weight: 1)]
class GroupTest_Users_Controller
{
    public function __invoke() {}
}
#[Group(name: 'profiles', parent: 'User Management', weight: 2)]
class GroupTest_Profiles_Controller
{
    public function __invoke() {}
}

it('generates multiple x-tagGroups with correct ordering', function () {
    RouteFacade::get('api/products', GroupTest_Products_Controller::class);
    RouteFacade::get('api/users2', GroupTest_Users2_Controller::class);
    RouteFacade::get('api/profiles2', GroupTest_Profiles2_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/products', 'api/users2', 'api/profiles2']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['x-tagGroups'])->toBe([
        [
            'name' => 'Products',
            'tags' => ['products'],
        ],
        [
            'name' => 'User Management',
            'tags' => ['users2', 'profiles2'],
        ],
    ]);
});
#[Group(name: 'products', parent: 'Products', weight: 0)]
class GroupTest_Products_Controller
{
    public function __invoke() {}
}
#[Group(name: 'users2', parent: 'User Management', weight: 1)]
class GroupTest_Users2_Controller
{
    public function __invoke() {}
}
#[Group(name: 'profiles2', parent: 'User Management', weight: 2)]
class GroupTest_Profiles2_Controller
{
    public function __invoke() {}
}

it('does not add x-tagGroups when no tags have parent', function () {
    RouteFacade::get('api/simple', GroupTest_Simple_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/simple');

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc)->not->toHaveKey('x-tagGroups');
});
#[Group(name: 'simple')]
class GroupTest_Simple_Controller
{
    public function __invoke() {}
}

it('includes ungrouped tags in tags array but not in x-tagGroups', function () {
    RouteFacade::get('api/grouped', GroupTest_Grouped_Controller::class);
    RouteFacade::get('api/ungrouped', GroupTest_Ungrouped_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/grouped', 'api/ungrouped']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['tags'])->toHaveCount(2)
        ->and(array_column($openApiDoc['tags'], 'name'))->toBe(['grouped', 'ungrouped'])
        ->and($openApiDoc['x-tagGroups'])->toBe([[
            'name' => 'My Group',
            'tags' => ['grouped'],
        ]]);
});
#[Group(name: 'grouped', parent: 'My Group')]
class GroupTest_Grouped_Controller
{
    public function __invoke() {}
}
#[Group(name: 'ungrouped')]
class GroupTest_Ungrouped_Controller
{
    public function __invoke() {}
}

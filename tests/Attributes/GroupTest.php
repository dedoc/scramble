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

it('sets native parent field on tags with parent parameter', function () {
    RouteFacade::get('api/users', GroupTest_Users_Controller::class);
    RouteFacade::get('api/profiles', GroupTest_Profiles_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/users', 'api/profiles']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc)->not->toHaveKey('x-tagGroups')
        ->and(collect($openApiDoc['tags'])->firstWhere('name', 'users'))->toMatchArray([
            'name' => 'users',
            'parent' => 'User Management',
        ])
        ->and(collect($openApiDoc['tags'])->firstWhere('name', 'profiles'))->toMatchArray([
            'name' => 'profiles',
            'parent' => 'User Management',
        ]);
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

it('auto-creates missing parent tags', function () {
    RouteFacade::get('api/users', GroupTest_Users_Controller::class);
    RouteFacade::get('api/profiles', GroupTest_Profiles_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/users', 'api/profiles']));

    $openApiDoc = app()->make(Generator::class)();

    $tagNames = array_column($openApiDoc['tags'], 'name');

    expect($tagNames)->toContain('User Management');
});

it('sets native parent field with multiple groups and correct ordering', function () {
    RouteFacade::get('api/products', GroupTest_Products_Controller::class);
    RouteFacade::get('api/users2', GroupTest_Users2_Controller::class);
    RouteFacade::get('api/profiles2', GroupTest_Profiles2_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/products', 'api/users2', 'api/profiles2']));

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc)->not->toHaveKey('x-tagGroups')
        ->and(collect($openApiDoc['tags'])->firstWhere('name', 'products'))->toMatchArray([
            'name' => 'products',
            'parent' => 'Products',
        ])
        ->and(collect($openApiDoc['tags'])->firstWhere('name', 'users2'))->toMatchArray([
            'name' => 'users2',
            'parent' => 'User Management',
        ])
        ->and(collect($openApiDoc['tags'])->firstWhere('name', 'profiles2'))->toMatchArray([
            'name' => 'profiles2',
            'parent' => 'User Management',
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

it('does not set parent field when no tags have parent', function () {
    RouteFacade::get('api/simple', GroupTest_Simple_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/simple');

    $openApiDoc = app()->make(Generator::class)();

    $tag = collect($openApiDoc['tags'])->firstWhere('name', 'simple');

    expect($tag)->not->toHaveKey('parent')
        ->and($openApiDoc)->not->toHaveKey('x-tagGroups');
});
#[Group(name: 'simple')]
class GroupTest_Simple_Controller
{
    public function __invoke() {}
}

it('includes ungrouped tags without parent field alongside grouped tags', function () {
    RouteFacade::get('api/grouped', GroupTest_Grouped_Controller::class);
    RouteFacade::get('api/ungrouped', GroupTest_Ungrouped_Controller::class);

    Scramble::routes(fn (Route $r) => in_array($r->uri, ['api/grouped', 'api/ungrouped']));

    $openApiDoc = app()->make(Generator::class)();

    $groupedTag = collect($openApiDoc['tags'])->firstWhere('name', 'grouped');
    $ungroupedTag = collect($openApiDoc['tags'])->firstWhere('name', 'ungrouped');

    expect($groupedTag)->toMatchArray([
        'name' => 'grouped',
        'parent' => 'My Group',
    ])
        ->and($ungroupedTag)->not->toHaveKey('parent')
        ->and($openApiDoc)->not->toHaveKey('x-tagGroups');
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

it('sets summary field on tag', function () {
    $openApiDoc = generateForRoute(fn () => RouteFacade::get('api/summary-test', GroupTest_Summary_Controller::class));

    expect($openApiDoc['tags'][0])->toMatchArray([
        'name' => 'Summarized',
        'description' => 'Full description',
        'summary' => 'Short summary',
    ]);
});
#[Group(name: 'Summarized', description: 'Full description', summary: 'Short summary')]
class GroupTest_Summary_Controller
{
    public function __invoke() {}
}

it('sets kind field on tag', function () {
    $openApiDoc = generateForRoute(fn () => RouteFacade::get('api/kind-test', GroupTest_Kind_Controller::class));

    expect($openApiDoc['tags'][0])->toMatchArray([
        'name' => 'ApiTag',
        'kind' => 'api',
    ]);
});
#[Group(name: 'ApiTag', kind: 'api')]
class GroupTest_Kind_Controller
{
    public function __invoke() {}
}

it('sets externalDocs on tag', function () {
    $openApiDoc = generateForRoute(fn () => RouteFacade::get('api/extdocs-test', GroupTest_ExternalDocs_Controller::class));

    expect($openApiDoc['tags'][0])->toMatchArray([
        'name' => 'Documented',
        'externalDocs' => [
            'description' => 'More info',
            'url' => 'https://example.com/docs',
        ],
    ]);
});
#[Group(name: 'Documented', externalDocsUrl: 'https://example.com/docs', externalDocsDescription: 'More info')]
class GroupTest_ExternalDocs_Controller
{
    public function __invoke() {}
}

it('sets externalDocs on tag with url only', function () {
    $openApiDoc = generateForRoute(fn () => RouteFacade::get('api/extdocs-url-test', GroupTest_ExternalDocsUrl_Controller::class));

    expect($openApiDoc['tags'][0])->toMatchArray([
        'name' => 'UrlOnly',
        'externalDocs' => [
            'url' => 'https://example.com/docs',
        ],
    ]);
});
#[Group(name: 'UrlOnly', externalDocsUrl: 'https://example.com/docs')]
class GroupTest_ExternalDocsUrl_Controller
{
    public function __invoke() {}
}

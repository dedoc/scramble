<?php

namespace Dedoc\Scramble\Tests\DocumentTransformers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('scramble.groups.enabled', true);
});

it('does nothing when the feature is disabled (backward compatible)', function () {
    config()->set('scramble.groups.enabled', false);

    $document = generateForRoute(fn () => Route::get('test-flat', TagGroupsTest_ControllerA::class));

    expect($document)->not->toHaveKey('x-tree');
    expect($document)->not->toHaveKey('x-tagGroups');
});

it('generates parent tag and x-tree from the #[Group] attribute', function () {
    $document = generateForRoute(fn () => Route::get('test-attr', TagGroupsTest_ControllerA::class));

    $usersTag = collect($document['tags'] ?? [])->firstWhere('name', 'Users');
    expect($usersTag)->not->toBeNull()
        ->and($usersTag['parent'])->toBe('Admin');

    $admin = collect($document['x-tree'] ?? [])->firstWhere('name', 'Admin');
    expect($admin)->not->toBeNull()
        ->and($admin['depth'])->toBe(0);

    $users = collect($admin['children'])->firstWhere('name', 'Users');
    expect($users)->not->toBeNull()
        ->and($users['parent'])->toBe('Admin')
        ->and($users['depth'])->toBe(1)
        ->and($users['routes'][0]['path'])->toBe('test-attr');
});

it('assigns only the leaf group as the operation tag', function () {
    $document = generateForRoute(fn () => Route::get('test-attr-leaf', TagGroupsTest_ControllerA::class));

    $operation = $document['paths']['/test-attr-leaf']['get'];
    expect($operation['tags'])->toBe(['Users']);
});

it('generates x-tagGroups and a nested tree from config rules', function () {
    config()->set('scramble.groups.rules', [
        ['group' => 'ConfigGroup/SubGroup', 'match' => ['prefix' => 'test-config*']],
    ]);

    $document = generateForRoute(fn () => Route::get('test-config-route', TagGroupsTest_ControllerB::class));

    $group = collect($document['x-tagGroups'] ?? [])->firstWhere('name', 'ConfigGroup');
    expect($group)->not->toBeNull()
        ->and($group['tags'])->toContain('SubGroup');

    $node = collect($document['x-tree'] ?? [])->firstWhere('name', 'ConfigGroup');
    expect($node)->not->toBeNull()
        ->and(collect($node['children'])->firstWhere('name', 'SubGroup'))->not->toBeNull();
});

it('resolves nested Laravel route group prefixes', function () {
    $document = generateForRoute(function () {
        $route = null;
        Route::prefix('admin-panel')->name('admin.')->group(function () use (&$route) {
            Route::prefix('billing-info')->group(function () use (&$route) {
                $route = Route::get('test-route-group', TagGroupsTest_ControllerC::class);
            });
        });

        return $route;
    });

    $admin = collect($document['x-tree'] ?? [])->firstWhere('name', 'AdminPanel');
    expect($admin)->not->toBeNull();

    $billing = collect($admin['children'])->firstWhere('name', 'BillingInfo');
    expect($billing)->not->toBeNull()
        ->and($billing['routes'])->not->toBeEmpty();
});

it('groups routes by a middleware config rule', function () {
    config()->set('scramble.groups.rules', [
        ['group' => 'Secured/Admin', 'match' => ['middleware' => 'auth:admin']],
    ]);

    $document = generateForRoute(
        fn () => Route::get('test-mw', TagGroupsTest_ControllerB::class)->middleware('auth:admin')
    );

    $secured = collect($document['x-tree'] ?? [])->firstWhere('name', 'Secured');
    expect($secured)->not->toBeNull()
        ->and(collect($secured['children'])->firstWhere('name', 'Admin'))->not->toBeNull();
});

it('groups every operation of a resource controller under one group', function () {
    $document = generateForRoute(function () {
        Route::apiResource('test-photos', TagGroupsTest_ResourceController::class);

        return app('router')->getRoutes()->getByName('test-photos.index');
    });

    $photos = collect($document['x-tree'] ?? [])->firstWhere('name', 'Photos');
    expect($photos)->not->toBeNull();

    // index (GET) and store (POST) share the `test-photos` URI and must both be grouped.
    $methods = collect($photos['routes'])->pluck('method')->sort()->values()->all();
    expect($methods)->toBe(['get', 'post']);
});

it('falls back to the controller name when nothing else resolves', function () {
    $document = generateForRoute(fn () => Route::get('test-fallback', TagGroupsTest_ControllerB::class));

    // The "Controller" token is stripped from the class base name:
    // TagGroupsTest_ControllerB -> TagGroupsTest_B.
    $node = collect($document['x-tree'] ?? [])->firstWhere('name', 'TagGroupsTest_B');
    expect($node)->not->toBeNull();
});

#[Group('Users', parent: 'Admin')]
class TagGroupsTest_ControllerA
{
    public function __invoke()
    {
        return 'test';
    }
}

class TagGroupsTest_ControllerB
{
    public function __invoke()
    {
        return 'test';
    }
}

class TagGroupsTest_ControllerC
{
    public function __invoke()
    {
        return 'test';
    }
}

#[Group('Photos')]
class TagGroupsTest_ResourceController
{
    public function index()
    {
        return 'index';
    }

    public function store()
    {
        return 'store';
    }

    public function show(string $id)
    {
        return 'show';
    }

    public function update(string $id)
    {
        return 'update';
    }

    public function destroy(string $id)
    {
        return 'destroy';
    }
}

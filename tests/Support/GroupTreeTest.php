<?php

namespace Dedoc\Scramble\Tests\Support;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\GroupTree\FallbackGroupResolver;
use Dedoc\Scramble\Support\GroupTree\GroupPathSplitter;
use Dedoc\Scramble\Support\GroupTree\GroupResolverPipeline;
use Dedoc\Scramble\Support\GroupTree\TreeBuilder;
use Illuminate\Routing\Route;

dataset('splitter paths', [
    ['Admin/Security', ['Admin', 'Security']],
    ['Admin > Security > Users', ['Admin', 'Security', 'Users']],
    ['Admin.Security', ['Admin', 'Security']],
    ['  /Admin/Security/  ', ['Admin', 'Security']],
    ['Single', ['Single']],
    ['', []],
    ['   ', []],
]);

it('splits group paths on /, > and . separators', function (string $input, array $expected) {
    expect(GroupPathSplitter::split($input))->toBe($expected);
})->with('splitter paths');

it('merges duplicate group paths and attaches every route to the shared leaf', function () {
    $tree = new TreeBuilder;
    $tree->addRoute(['Admin', 'Users'], ['id' => 'a', 'name' => 'a', 'type' => 'route']);
    $tree->addRoute(['Admin', 'Users'], ['id' => 'b', 'name' => 'b', 'type' => 'route']);

    $roots = $tree->toArray();

    expect($roots)->toHaveCount(1)
        ->and($roots[0]['name'])->toBe('Admin')
        ->and($roots[0]['children'])->toHaveCount(1)
        ->and($roots[0]['children'][0]['name'])->toBe('Users')
        ->and($roots[0]['children'][0]['routes'])->toHaveCount(2);
});

it('supports unlimited nesting with correct depth', function () {
    $tree = new TreeBuilder;
    $tree->addRoute(['A', 'B', 'C', 'D'], ['id' => 'x', 'name' => 'x', 'type' => 'route']);

    $roots = $tree->toArray();
    $a = $roots[0];
    $b = $a['children'][0];
    $c = $b['children'][0];
    $d = $c['children'][0];

    expect($a['depth'])->toBe(0)
        ->and($b['depth'])->toBe(1)
        ->and($c['depth'])->toBe(2)
        ->and($d['depth'])->toBe(3)
        ->and($d['routes'])->toHaveCount(1);
});

it('orders sibling groups by explicit order then natural name', function () {
    $tree = new TreeBuilder;
    $tree->addRoute(['Zebra'], ['id' => 'z', 'name' => 'z', 'type' => 'route'], ['Zebra' => 1]);
    $tree->addRoute(['Apple'], ['id' => 'a', 'name' => 'a', 'type' => 'route'], ['Apple' => 2]);
    $tree->addRoute(['Mango'], ['id' => 'm', 'name' => 'm', 'type' => 'route']);

    $names = array_column($tree->toArray(), 'name');

    // Zebra (order 1) and Apple (order 2) come before Mango (default order, sorted by name).
    expect($names)->toBe(['Zebra', 'Apple', 'Mango']);
});

it('falls back to the controller name without the Controller suffix', function () {
    $resolver = new FallbackGroupResolver;
    $route = new Route(['GET'], '/users', ['controller' => 'App\\Http\\Controllers\\UserController@index']);

    expect($resolver->resolve(new Operation('get'), $route))->toBe(['User']);
});

it('falls back to the configured default group for closure routes', function () {
    $resolver = new FallbackGroupResolver('Misc');
    $route = new Route(['GET'], '/ping', ['uses' => fn () => 'pong']);

    expect($resolver->resolve(new Operation('get'), $route))->toBe(['Misc']);
});

it('returns the first non-empty path from the pipeline', function () {
    $pipeline = new GroupResolverPipeline;
    $route = new Route(['GET'], '/users', ['controller' => 'App\\Http\\Controllers\\UserController@index']);

    // No attribute, config rules or prefix -> fallback resolver wins.
    expect($pipeline->resolve(new Operation('get'), $route))->toBe(['User']);
});

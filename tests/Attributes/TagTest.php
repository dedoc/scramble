<?php

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('describes a parent tag that holds no endpoints of its own', function () {
    RouteFacade::get('api/a', TagTest_A_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/a');

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['tags'])->toBe([
        ['name' => 'Restaurants', 'description' => 'Public restaurant profiles.', 'parent' => 'Browsing'],
        ['name' => 'Browsing', 'description' => 'What anyone can look at without an account.'],
    ]);
});

it('leaves the endpoint in its own group', function () {
    RouteFacade::get('api/a', TagTest_A_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/a');

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['paths']['/a']['get']['tags'])->toBe(['Restaurants']);
});

#[Group('Restaurants', 'Public restaurant profiles.', parent: 'Browsing')]
#[Tag('Browsing', 'What anyone can look at without an account.')]
class TagTest_A_Controller
{
    public function __invoke() {}
}

it('declares more than one tag from a single class', function () {
    RouteFacade::get('api/b', TagTest_B_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/b');

    $openApiDoc = app()->make(Generator::class)();

    expect(collect($openApiDoc['tags'])->pluck('name')->all())
        ->toBe(['Orders', 'Ordering', 'Browsing']);
});

#[Group('Orders', parent: 'Ordering')]
#[Tag('Ordering', 'Building a cart and paying for it.')]
#[Tag('Browsing', 'What anyone can look at without an account.')]
class TagTest_B_Controller
{
    public function __invoke() {}
}

it('nests a parent under a parent of its own', function () {
    RouteFacade::get('api/c', TagTest_C_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/c');

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['tags'])->toBe([
        ['name' => 'Invoices', 'parent' => 'Finances'],
        ['name' => 'Finances', 'description' => 'What was earned and charged.', 'parent' => 'Business'],
        ['name' => 'Business'],
    ]);
});

#[Group('Invoices', parent: 'Finances')]
#[Tag('Finances', 'What was earned and charged.', parent: 'Business')]
class TagTest_C_Controller
{
    public function __invoke() {}
}

it('keeps what the group states when a tag declares the same name', function () {
    RouteFacade::get('api/d', TagTest_D_Controller::class);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/d');

    $openApiDoc = app()->make(Generator::class)();

    expect($openApiDoc['tags'][0])
        ->toBe(['name' => 'Catalog', 'description' => 'What the group says.']);
});

#[Group('Catalog', 'What the group says.')]
#[Tag('Catalog', 'What the declaration says.')]
class TagTest_D_Controller
{
    public function __invoke() {}
}

<?php

namespace Dedoc\Scramble\Tests\Configuration;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\ExternalDocumentation;
use Dedoc\Scramble\Support\Generator\Tag;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['scramble.openapi_version' => OpenApiVersion::V3_2]);
});

it('describes a parent centrally without assigning endpoints to it', function () {
    Scramble::configure()->withTags([
        new Tag(name: 'Catalogue', description: 'Everything available for purchase.', kind: 'nav'),
    ]);

    $document = generateForRoute(fn () => Route::get('api/products', TagsTest_ProductsController::class));

    expect($document['tags'])->toBe([
        ['name' => 'Catalogue', 'description' => 'Everything available for purchase.', 'kind' => 'nav'],
        ['name' => 'Products', 'description' => 'Product operations.', 'summary' => 'Products summary', 'parent' => 'Catalogue'],
    ])->and($document['paths']['/products']['get']['tags'])->toBe(['Products']);
});

it('uses configured metadata and fills unspecified fields from groups', function () {
    $tag = new Tag('Products', 'Configured description.', parent: 'Store', kind: 'badge');
    $tag->setExtensionProperty('displayName', 'Available products');
    Scramble::configure()->withTags([$tag]);

    $document = generateForRoute(fn () => Route::get('api/products', TagsTest_ProductsController::class));

    expect($document['tags'])->toBe([
        [
            'name' => 'Products',
            'description' => 'Configured description.',
            'summary' => 'Products summary',
            'parent' => 'Store',
            'kind' => 'badge',
            'x-displayName' => 'Available products',
        ],
        ['name' => 'Store'],
    ])->and($tag->summary)->toBeNull();
});

it('includes configured tags without any matching routes and creates their missing parents', function () {
    $config = Scramble::configure()->useConfig(config('scramble'))
        ->routes(fn () => false)
        ->withTags([
            new Tag('Catalogue', parent: 'Business'),
            new Tag('Help', externalDocs: new ExternalDocumentation('https://example.com/docs')),
        ]);

    $document = app(Generator::class)($config);

    expect($document['tags'])->toBe([
        ['name' => 'Catalogue', 'parent' => 'Business'],
        ['name' => 'Help', 'externalDocs' => ['url' => 'https://example.com/docs']],
        ['name' => 'Business'],
    ]);
});

it('serializes configured tags for the selected OpenAPI version', function (OpenApiVersion $version) {
    config(['scramble.openapi_version' => $version]);
    Scramble::configure()->withTags([
        new Tag('Catalogue', 'Available products.', summary: 'Browse', kind: 'nav'),
    ]);

    $document = generateForRoute(fn () => Route::get('api/products', TagsTest_ProductsController::class));

    expect($document['tags'][0])->toBe($version === OpenApiVersion::V3_2
        ? ['name' => 'Catalogue', 'description' => 'Available products.', 'summary' => 'Browse', 'kind' => 'nav']
        : ['name' => 'Catalogue', 'description' => 'Available products.']);
})->with([OpenApiVersion::V3_1, OpenApiVersion::V3_2]);

it('inherits configured tags without sharing mutable objects between APIs', function () {
    Scramble::configure()->withTags([
        new Tag('Catalogue', externalDocs: new ExternalDocumentation('https://example.com/docs')),
    ]);

    $otherApi = Scramble::registerApi('other');
    $otherApi->tags[0]->description = 'Other API catalogue.';
    $otherApi->tags[0]->externalDocs->url = 'https://example.com/other';

    expect(Scramble::configure()->tags[0]->description)->toBeNull()
        ->and(Scramble::configure()->tags[0]->externalDocs->url)->toBe('https://example.com/docs');
});

it('keeps generated document mutations out of configuration and subsequent generations', function () {
    $config = Scramble::configure()->useConfig(config('scramble'))
        ->routes(fn () => false)
        ->withTags([new Tag('Help', externalDocs: new ExternalDocumentation('https://example.com/docs'))]);

    $first = app(Generator::class)->generate($config);
    $first->openApi->tags[0]->externalDocs->url = 'https://example.com/changed';

    $second = app(Generator::class)($config);

    expect($second['tags'][0]['externalDocs']['url'])->toBe('https://example.com/docs');
});

#[Group('Products', 'Product operations.', parent: 'Catalogue', summary: 'Products summary')]
class TagsTest_ProductsController
{
    public function __invoke() {}
}

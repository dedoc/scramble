<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Tests\TestUtils;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;

test('types widening', function (string $type, string $expectedType) {
    $type = TestUtils::parseType($type);

    expect($type->widen()->toString())->toBe($expectedType);
})->with([
    ['true|false', 'boolean'],
    ['true|false|true', 'boolean'],
    ['int|42', 'int'],
    ['42|69', 'int(42)|int(69)'],
    ['string|"wow"', 'string'],
]);

test('widens allowed key value generic collections', function (string $collectionClass) {
    $type = TestUtils::parseType("$collectionClass<int, string>|$collectionClass<string, int>");

    expect($type->widen()->toString())->toBe("$collectionClass<int|string, string|int>");
})->with([
    Collection::class,
    EloquentCollection::class,
    LazyCollection::class,
    Enumerable::class,
]);

test('widens Enumerable subtype|supertype into the more specific type', function () {
    $type = TestUtils::parseType(EloquentCollection::class.'<int, string>|'.Collection::class.'<int, string>');

    expect($type->widen()->toString())->toBe(EloquentCollection::class.'<int, string>');
});

test('widens Enumerable subtype|supertype with differing templates', function () {
    $type = TestUtils::parseType(EloquentCollection::class.'<int, string>|'.Collection::class.'<string, int>');

    expect($type->widen()->toString())->toBe(EloquentCollection::class.'<int|string, string|int>');
});

test('does not widen anonymous resource collection templates as key value pairs', function () {
    $type = TestUtils::parseType(AnonymousResourceCollection::class.'<unknown, array<mixed>, App\Http\Brands\Events\Resources\EventResource>'
        .'|'.AnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.', array<mixed>, App\Http\Brands\Events\Resources\EventResource>');

    expect($type->widen()->toString())->toBe(AnonymousResourceCollection::class.'<unknown, array<mixed>, App\Http\Brands\Events\Resources\EventResource>'
        .'|'.AnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.', array<mixed>, App\Http\Brands\Events\Resources\EventResource>');
});

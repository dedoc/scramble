<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWidener;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Dedoc\Scramble\Tests\TestUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\Conditionable;

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

test('preserves missing value when widening mixed types', function () {
    $type = new Union([
        new MixedType,
        new ObjectType(MissingValue::class),
    ]);

    expect($type->widen()->toString())->toBe('mixed|'.MissingValue::class)
        ->and((new Union([new MixedType, new StringType]))->widen()->toString())->toBe('mixed');
});

test('widens allowed key value generic collections', function (string $collectionClass) {
    $type = TestUtils::parseType("$collectionClass<int, string>|$collectionClass<string, int>");

    expect($type->widen()->toString())->toBe("$collectionClass<int|string, string|int>");
})->with([
    Collection::class,
    EloquentCollection::class,
    LazyCollection::class,
    Enumerable::class,
]);

test('does not widen anonymous resource collection templates as key value pairs', function () {
    $type = TestUtils::parseType(AnonymousResourceCollection::class.'<unknown, array<mixed>, App\Http\Brands\Events\Resources\EventResource>'
        .'|'.AnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.', array<mixed>, App\Http\Brands\Events\Resources\EventResource>');

    expect($type->widen()->toString())->toBe(AnonymousResourceCollection::class.'<unknown, array<mixed>, App\Http\Brands\Events\Resources\EventResource>'
        .'|'.AnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.', array<mixed>, App\Http\Brands\Events\Resources\EventResource>');
});

test('keeps equivalent self types collapsed through conditional builder chains', function () {
    app()->instance(TypeWidener::class, $widener = new RecordingTypeWidener);

    $type = getStatementType(
        '(new '.ConditionalBuilderChain::class.')->applyFilters('.SampleUserModel::class.'::query())',
    );

    expect($type->toString())->toBe(Builder::class.'|'.ConditionalBuilderChain::class)
        ->and($widener->largestUnionSize)->toBe(2);
});

class RecordingTypeWidener extends TypeWidener
{
    public int $largestUnionSize = 0;

    public function widen(array $types): Type
    {
        $this->largestUnionSize = max($this->largestUnionSize, count($types));

        return parent::widen($types);
    }
}

class ConditionalBuilderChain
{
    use Conditionable;

    public function applyFilters(Builder $builder)
    {
        return $builder
            ->when(true, fn (): static => $this)
            ->when(true, fn (): static => $this)
            ->when(true, fn (): static => $this)
            ->when(true, fn (): static => $this);
    }
}

<?php

use Dedoc\Scramble\Support\InferExtensions\PaginateMethodsReturnTypeExtension;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

it('guesses paginate type', function (string $expression, string $expectedTypeString) {
    $type = getStatementType($expression, [
        new PaginateMethodsReturnTypeExtension,
    ]);

    expect($type->toString())->toBe($expectedTypeString);
})->with([
    [SampleUserModel::class.'::paginate()', LengthAwarePaginator::class.'<int, unknown>'],
    [SampleUserModel::class.'::query()->paginate()', LengthAwarePaginator::class.'<int, unknown>'],
    [SampleUserModel::class.'::query()->fastPaginate()', LengthAwarePaginator::class.'<int, unknown>'],
    [SampleUserModel::class.'::query()->where("foo", "bar")->paginate()', LengthAwarePaginator::class.'<int, unknown>'],

    [SampleUserModel::class.'::cursorPaginate()', CursorPaginator::class.'<int, unknown>'],
    [SampleUserModel::class.'::query()->cursorPaginate()', CursorPaginator::class.'<int, unknown>'],

    [SampleUserModel::class.'::simplePaginate()', Paginator::class.'<int, unknown>'],
    [SampleUserModel::class.'::query()->simplePaginate()', Paginator::class.'<int, unknown>'],
]);

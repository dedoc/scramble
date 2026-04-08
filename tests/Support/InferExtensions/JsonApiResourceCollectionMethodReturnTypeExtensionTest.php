<?php

use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiAnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest extends JsonApiResource
{
    public $attributes = ['name', 'email'];
}

it('resolves correct response type for JsonApi collection methods', function (string $expression, string $expectedTypeString) {
    $type = getStatementType($expression);

    expect($type->toString())->toBe($expectedTypeString);
})->with([
    'all response' => [
        JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest::class.'::collection('.SampleUserModel::class.'::all())->response()',
        JsonResponse::class.'<'.ResourceResponse::class.'<'.JsonApiAnonymousResourceCollection::class.'<'.Collection::class.'<int, '.SampleUserModel::class.'>, array<mixed>, JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest>>, unknown, array{Content-type: string(application/vnd.api+json)}>',
    ],
    'all toResponse' => [
        JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest::class.'::collection('.SampleUserModel::class.'::all())->toResponse()',
        JsonResponse::class.'<'.ResourceResponse::class.'<'.JsonApiAnonymousResourceCollection::class.'<'.Collection::class.'<int, '.SampleUserModel::class.'>, array<mixed>, JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest>>, unknown, array{Content-type: string(application/vnd.api+json)}>',
    ],
    'paginate toResponse' => [
        JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest::class.'::collection('.SampleUserModel::class.'::paginate())->toResponse()',
        JsonResponse::class.'<'.PaginatedResourceResponse::class.'<'.JsonApiAnonymousResourceCollection::class.'<'.LengthAwarePaginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest>>, unknown, array{Content-type: string(application/vnd.api+json)}>',
    ],
    'simplePaginate toResponse' => [
        JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest::class.'::collection('.SampleUserModel::class.'::simplePaginate())->toResponse()',
        JsonResponse::class.'<'.PaginatedResourceResponse::class.'<'.JsonApiAnonymousResourceCollection::class.'<'.Paginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest>>, unknown, array{Content-type: string(application/vnd.api+json)}>',
    ],
    'cursorPaginate toResponse' => [
        JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest::class.'::collection('.SampleUserModel::class.'::cursorPaginate())->toResponse()',
        JsonResponse::class.'<'.PaginatedResourceResponse::class.'<'.JsonApiAnonymousResourceCollection::class.'<'.CursorPaginator::class.'<int, '.SampleUserModel::class.'>, array<mixed>, JsonApiResource_JsonApiResourceCollectionMethodReturnTypeExtensionTest>>, unknown, array{Content-type: string(application/vnd.api+json)}>',
    ],
]);

<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

class User_AnonymousResourceCollectionTypeToSchemaTest extends Model
{
    protected $table = 'users';
}

/**
 * @mixin User_AnonymousResourceCollectionTypeToSchemaTest
 */
class UserResource_AnonymousResourceCollectionTypeToSchemaTest extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => $this->id];
    }
}

beforeEach(function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);

    $this->transformer = app(TypeTransformer::class, [
        'context' => $context,
    ]);
});

it('documents inferred pagination response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', InferredPagination_AnonymousResourceCollectionTypeToSchemaTestController::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->and($schema = $responses[200]['content']['application/json']['schema'])
        ->toHaveKeys(['type', 'properties'])
        ->and($schema['properties'])
        ->toHaveKeys(['data', 'meta', 'links']);
});

it('documents paginated response from service builder union chain', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', ServiceBuilderPagination_AnonymousResourceCollectionTypeToSchemaTestController::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->and($schema = $responses[200]['content']['application/json']['schema'])
        ->toHaveKeys(['type', 'properties'])
        ->and($schema['properties'])
        ->toHaveKeys(['data', 'meta', 'links']);
});
class ServiceBuilderPagination_AnonymousResourceCollectionTypeToSchemaTestController
{
    public function __invoke()
    {
        return UserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(
            (new UserService_AnonymousResourceCollectionTypeToSchemaTest)
                ->getByName('Test', true)
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(4)
        );
    }
}
class UserService_AnonymousResourceCollectionTypeToSchemaTest
{
    /**
     * @return (\Illuminate\Database\Eloquent\Builder<User_AnonymousResourceCollectionTypeToSchemaTest>|Illuminate\Database\Eloquent\Collection<int, User_AnonymousResourceCollectionTypeToSchemaTest>)
     */
    public function getByName(string $name, bool $toBuilder = false): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Collection
    {
        $query = User_AnonymousResourceCollectionTypeToSchemaTest::query()->where('name', '=', $name);

        return $toBuilder ? $query : $query->get();
    }
}

it('documents paginated response when service returns LengthAwarePaginator', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', [ServicePaginatorPagination_AnonymousResourceCollectionTypeToSchemaTestController::class, 'myHandler']));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->and($schema = $responses[200]['content']['application/json']['schema'])
        ->toHaveKeys(['type', 'properties'])
        ->and($schema['properties'])
        ->toHaveKeys(['data', 'meta', 'links']);
});
class ServicePaginatorPagination_AnonymousResourceCollectionTypeToSchemaTestController
{
    public function __construct(private UserServicePaginator_AnonymousResourceCollectionTypeToSchemaTest $userService) {}

    public function myHandler(Request $request): \Illuminate\Http\Resources\Json\ResourceCollection
    {
        return UserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(
            $this->userService->allUsers()
        );
    }
}

class UserServicePaginator_AnonymousResourceCollectionTypeToSchemaTest
{
    /**
     * @return LengthAwarePaginator<int, User_AnonymousResourceCollectionTypeToSchemaTest>
     */
    public function allUsers(): LengthAwarePaginator
    {
        return User_AnonymousResourceCollectionTypeToSchemaTest::query()->paginate();
    }
}
class InferredPagination_AnonymousResourceCollectionTypeToSchemaTestController
{
    public function __invoke()
    {
        return UserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(User_AnonymousResourceCollectionTypeToSchemaTest::paginate());
    }
}

it('documents manually created response', function () {
    $type = getStatementType(UserResource_AnonymousResourceCollectionTypeToSchemaTest::class.'::collection()->response()->setStatusCode(202)');

    $response = $this->transformer->toResponse($type);

    expect($response->code)
        ->toBe(202)
        ->and($response->toArray()['description'])
        ->toBe('Array of `UserResource_AnonymousResourceCollectionTypeToSchemaTest`');
});

it('documents manually annotated response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', ManualResponse_AnonymousResourceCollectionTypeToSchemaTestController::class));

    $response = $openApiDocument['paths']['/test']['get']['responses'][200];

    expect($response['content']['application/json']['schema']['properties']['data'])
        ->toBe([
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/UserResource_AnonymousResourceCollectionTypeToSchemaTest',
            ],
        ])
        ->and($response['description'])
        ->toBe('Paginated set of `UserResource_AnonymousResourceCollectionTypeToSchemaTest`');
});
class ManualResponse_AnonymousResourceCollectionTypeToSchemaTestController
{
    /**
     * @return AnonymousResourceCollection<LengthAwarePaginator<UserResource_AnonymousResourceCollectionTypeToSchemaTest>>
     */
    public function __invoke()
    {
        return UserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(User_AnonymousResourceCollectionTypeToSchemaTest::all())->response();
    }
}

it('uses SchemaName attribute value in paginated response description', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', PaginatedSchemaName_AnonymousResourceCollectionTypeToSchemaTestController::class));

    $response = $openApiDocument['paths']['/test']['get']['responses'][200];

    expect($response['description'])
        ->toBe('Paginated set of `CustomUser`');
});

#[SchemaName('CustomUser')]
class CustomUserResource_AnonymousResourceCollectionTypeToSchemaTest extends JsonResource
{
    public function toArray(Request $request)
    {
        return ['id' => $this->id];
    }
}

class PaginatedSchemaName_AnonymousResourceCollectionTypeToSchemaTestController
{
    public function __invoke()
    {
        return CustomUserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(User_AnonymousResourceCollectionTypeToSchemaTest::paginate());
    }
}

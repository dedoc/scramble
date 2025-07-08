<?php

namespace Dedoc\Scramble\Tests\Support\TypeToSchemaExtensions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

it('documents inferred pagination response', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', InferredPagination_AnonymousResourceCollectionTypeToSchemaTestController::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKey(200)
        ->and($schema = $responses[200]['content']['application/json']['schema'])
        ->toHaveKeys(['type', 'properties'])
        ->and($schema['properties'])
        ->toHaveKeys(['data', 'meta', 'links']);
});
class InferredPagination_AnonymousResourceCollectionTypeToSchemaTestController
{
    public function __invoke()
    {
        return UserResource_AnonymousResourceCollectionTypeToSchemaTest::collection(User_AnonymousResourceCollectionTypeToSchemaTest::paginate());
    }
}

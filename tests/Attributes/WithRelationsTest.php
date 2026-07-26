<?php

use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\SchemaVariant;
use Dedoc\Scramble\Attributes\WithRelations;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\JsonResource\AppliesWithRelationsAttributes;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

it('documents whenLoaded fields required when WithRelations annotates the resource', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_PostController::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe([
            'allOf' => [
                ['$ref' => '#/components/schemas/WithRelationsTest_PostResource'],
                [
                    'type' => 'object',
                    'required' => ['user'],
                ],
            ],
        ]);
});
class WithRelationsTest_PostModel extends Model
{
    public function user()
    {
        return $this->belongsTo(WithRelationsTest_UserModel::class);
    }
}
class WithRelationsTest_UserModel extends Model {}
/**
 * @property WithRelationsTest_PostModel $resource
 */
class WithRelationsTest_PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
        ];
    }
}
class WithRelationsTest_PostController
{
    #[WithRelations(WithRelationsTest_PostResource::class, ['user'])]
    public function __invoke()
    {
        return new WithRelationsTest_PostResource(new WithRelationsTest_PostModel);
    }
}

it('applies WithRelations to a resource nested inside a collection', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_PostCollectionController::class));

    $schema = $openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'];

    expect($schema)->toHaveKey('properties.data.items')
        ->and($schema['properties']['data']['items'])->toBe([
            'allOf' => [
                ['$ref' => '#/components/schemas/WithRelationsTest_PostResource'],
                [
                    'type' => 'object',
                    'required' => ['user'],
                ],
            ],
        ]);
});
class WithRelationsTest_PostCollectionController
{
    #[WithRelations(WithRelationsTest_PostResource::class, ['user'])]
    public function __invoke()
    {
        return WithRelationsTest_PostResource::collection([]);
    }
}

it('applies WithRelations to a resource nested inside a paginator collection', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_PostPaginatedController::class));

    $items = $openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items'] ?? null;

    expect($items)->toBe([
        'allOf' => [
            ['$ref' => '#/components/schemas/WithRelationsTest_PostResource'],
            [
                'type' => 'object',
                'required' => ['user'],
            ],
        ],
    ]);
});
class WithRelationsTest_PostPaginatedController
{
    #[WithRelations(WithRelationsTest_PostResource::class, ['user'])]
    public function __invoke()
    {
        return WithRelationsTest_PostResource::collection(new LengthAwarePaginator([new WithRelationsTest_PostModel], 1, 15));
    }
}

it('matches SchemaVariant from WithRelations annotations', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_VariantController::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe(['$ref' => '#/components/schemas/PostWithUser']);
});
/**
 * @property WithRelationsTest_PostModel $resource
 */
#[SchemaVariant(name: 'PostList', default: true)]
#[SchemaVariant(name: 'PostWithUser', whenLoaded: ['user'])]
class WithRelationsTest_VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
        ];
    }
}
class WithRelationsTest_VariantController
{
    #[WithRelations(WithRelationsTest_VariantResource::class, ['user'])]
    public function __invoke()
    {
        return new WithRelationsTest_VariantResource(new WithRelationsTest_PostModel);
    }
}

it('supports WithRelations on controller routes', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_ControllerRouteController::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe([
            'allOf' => [
                ['$ref' => '#/components/schemas/WithRelationsTest_PostResource'],
                [
                    'type' => 'object',
                    'required' => ['user'],
                ],
            ],
        ]);
});
class WithRelationsTest_ControllerRouteController
{
    #[WithRelations(WithRelationsTest_PostResource::class, ['user'])]
    public function __invoke()
    {
        return new WithRelationsTest_PostResource(new WithRelationsTest_PostModel);
    }
}

it('merges annotation relations with already inferred eager loads', function () {
    $model = (new ObjectType(WithRelationsTest_PostModel::class))->withAssignedPropertyType(
        'relations',
        new KeyedArrayType([
            new ArrayItemType_(null, new LiteralStringType('author')),
        ], isList: true),
    );

    $applied = (new AppliesWithRelationsAttributes(app(Index::class)))->apply(
        new Generic(WithRelationsTest_PostResource::class, [$model]),
        [new WithRelations(WithRelationsTest_PostResource::class, ['comments'])],
    );

    expect(collect($applied->templateTypes[0]->propertyTypes['relations']->items)->map(
        fn (ArrayItemType_ $item) => $item->value instanceof LiteralStringType ? $item->value->value : null,
    )->all())->toBe(['author', 'comments']);
});

it('resolves the model from the resource definition when the instance model is unknown', function () {
    $applied = (new AppliesWithRelationsAttributes(app(Index::class)))->apply(
        new Generic(WithRelationsTest_PostResource::class, [new UnknownType]),
        [new WithRelations(WithRelationsTest_PostResource::class, ['user'])],
    );

    expect(collect($applied->templateTypes[0]->propertyTypes['relations']->items)->map(
        fn (ArrayItemType_ $item) => $item->value instanceof LiteralStringType ? $item->value->value : null,
    )->all())->toBe(['user']);
});

it('applies WithRelations to a resource type declared on the Response attribute', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', WithRelationsTest_ResponseAttributeController::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe([
            'allOf' => [
                ['$ref' => '#/components/schemas/WithRelationsTest_PostResource'],
                [
                    'type' => 'object',
                    'required' => ['user'],
                ],
            ],
        ]);
});
class WithRelationsTest_ResponseAttributeController
{
    #[WithRelations(WithRelationsTest_PostResource::class, ['user'])]
    #[Response(200, type: WithRelationsTest_PostResource::class)]
    public function __invoke()
    {
        return something_unknown();
    }
}

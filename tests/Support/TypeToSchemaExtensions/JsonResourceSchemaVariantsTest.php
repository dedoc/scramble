<?php

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\Attributes\SchemaVariant;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceResponseTypeToSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
    $this->infer = app(Infer::class);
});

function makeJsonResourceExtension(OpenApiContext $context): JsonResourceTypeToSchema
{
    $transformer = new TypeTransformer(app(Infer::class), $context, [
        JsonResourceTypeToSchema::class,
        AnonymousResourceCollectionTypeToSchema::class,
        ResourceResponseTypeToSchema::class,
    ]);

    return new JsonResourceTypeToSchema(
        app(Infer::class),
        $transformer,
        $context->openApi->components,
        $context,
    );
}

function modelWithRelations(string $modelClass, array $relations): ObjectType
{
    $modelType = new ObjectType($modelClass);

    $modelType->propertyTypes['relations'] = new KeyedArrayType(
        array_map(
            fn (string $relation) => new ArrayItemType_(null, new LiteralStringType($relation)),
            $relations,
        ),
    );

    return $modelType;
}

function resourceWithModel(string $resourceClass, Type $modelType): Generic
{
    return new Generic($resourceClass, [$modelType]);
}

it('documents relation-conditioned properties as required when relation is loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_BaseResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)
        ->toBe([
            'allOf' => [
                [
                    '$ref' => '#/components/schemas/SchemaVariantsTest_BaseResource',
                ],
                [
                    'type' => 'object',
                    'required' => ['user'],
                ],
            ],
        ])
        ->and($componentSchema)->toBeSameJson([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'user' => ['type' => 'object'],
                'team' => ['type' => 'object'],
            ],
            'required' => ['id'],
        ]);
});

it('ignores relation-conditioned properties when relation is not loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_BaseResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_BaseResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'user' => ['type' => 'object'],
            'team' => ['type' => 'object'],
        ],
        'required' => ['id'],
    ]);
});

it('falls back to optional relation fields when model state is unknown', function () {
    $type = new Generic(SchemaVariantsTest_BaseResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_BaseResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'user' => ['type' => 'object'],
            'team' => ['type' => 'object'],
        ],
        'required' => ['id'],
    ]);
});

it('uses default variant schema when relations unknown', function () {
    $type = new Generic(SchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema('AccountList')
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/AccountList',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('can match no loaded relations variant', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_NoDefaultVariantResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema('Account')
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/Account',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('does not reuse cached schema when another variant of the same resource was transformed first', function () {
    $context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);

    $transformer = new TypeTransformer(app(Infer::class), $context, [
        JsonResourceTypeToSchema::class,
        ResourceResponseTypeToSchema::class,
    ]);

    $resourceClass = SchemaVariantsTest_PriorityVariantResource::class;
    $modelClass = SchemaVariantsTest_PostModel::class;

    $extendedType = resourceWithModel($resourceClass, modelWithRelations($modelClass, ['user', 'team']));
    $specificType = resourceWithModel($resourceClass, modelWithRelations($modelClass, ['user']));

    expect($extendedType->toString())->toBe($specificType->toString());

    $extendedSchema = $transformer->transform($extendedType)->toArray();
    $specificSchema = $transformer->transform($specificType)->toArray();

    $specificResponse = $transformer->toResponse(new Generic(
        Illuminate\Http\Resources\Json\ResourceResponse::class,
        [$specificType],
    ));

    expect($extendedSchema)->toBe([
        '$ref' => '#/components/schemas/ExtendedAccount',
    ])->and($specificSchema)->toBe([
        '$ref' => '#/components/schemas/AccountWithUser',
    ])->and($specificResponse->description)->toBe('`AccountWithUser`');
});

it('loads correct variant based on matched relations priority', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_PriorityVariantResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema('AccountWithUser')
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/AccountWithUser',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'user' => ['type' => 'object'],
        ],
        'required' => ['id', 'user'],
    ]);
});

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
#[SchemaVariant(name: 'Account', whenLoaded: [])]
#[SchemaVariant(name: 'AccountWithUser', whenLoaded: ['user'])]
#[SchemaVariant(name: 'ExtendedAccount', whenLoaded: '*')]
class SchemaVariantsTest_PriorityVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
            'team' => $this->whenLoaded('team'),
        ];
    }
}

it('matches the most specific variant when relations are known', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_VariantResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['user', 'team']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/ExtendedAccount',
    ]);
});

it('uses default variant when relation state is unknown', function () {
    $type = new Generic(SchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/AccountList',
    ]);
});

it('uses base SchemaName when no default variant exists and relations are unknown', function () {
    $type = new Generic(SchemaVariantsTest_NamedResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema('CustomAccount')
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/CustomAccount',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'user' => ['type' => 'object'],
        ],
        'required' => ['id'],
    ]);
});

it('handles mergeWhen with relationLoaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_MergeWhenResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_MergeWhenResource::class)
        ->toArray();

    expect($schema)->toBe([
        'allOf' => [
            [
                '$ref' => '#/components/schemas/SchemaVariantsTest_MergeWhenResource',
            ],
            [
                'type' => 'object',
                'required' => ['profile'],
            ],
        ],
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'profile' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('keeps mergeWhen fields optional when relation is not loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_MergeWhenResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_MergeWhenResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_MergeWhenResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'profile' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('keeps mergeWhen fields optional when relation state is unknown', function () {
    $type = new Generic(SchemaVariantsTest_MergeWhenResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_MergeWhenResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_MergeWhenResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'profile' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('handles when with relationLoaded and a resource collection callback', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_WhenRelationLoadedCollectionResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['comments']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_WhenRelationLoadedCollectionResource::class)
        ->toArray();

    expect($schema)->toBe([
        'allOf' => [
            [
                '$ref' => '#/components/schemas/SchemaVariantsTest_WhenRelationLoadedCollectionResource',
            ],
            [
                'type' => 'object',
                'required' => ['comments'],
            ],
        ],
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/SchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('keeps a resource collection callback optional when its relation is not loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_WhenRelationLoadedCollectionResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_WhenRelationLoadedCollectionResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_WhenRelationLoadedCollectionResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/SchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('documents relation-conditioned resource collection when relation is loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_CollectionWhenLoadedResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['comments']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        'allOf' => [
            [
                '$ref' => '#/components/schemas/SchemaVariantsTest_CollectionWhenLoadedResource',
            ],
            [
                'type' => 'object',
                'required' => ['comments'],
            ],
        ],
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/SchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('excludes relation-conditioned resource collection when relation is not loaded', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_CollectionWhenLoadedResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_CollectionWhenLoadedResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/SchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('falls back to optional resource collection field when relation state is unknown', function () {
    $type = new Generic(SchemaVariantsTest_CollectionWhenLoadedResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(SchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/SchemaVariantsTest_CollectionWhenLoadedResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/SchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('throws when multiple variants match with equal specificity', function () {
    $type = resourceWithModel(
        SchemaVariantsTest_AmbiguousVariantResource::class,
        modelWithRelations(SchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);

    expect(fn () => $extension->toSchema($type))
        ->toThrow(LogicException::class, 'Ambiguous SchemaVariant match');
});

class SchemaVariantsTest_PostModel extends Model
{
    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTo(SchemaVariantsTest_UserModel::class);
    }

    public function team()
    {
        return $this->belongsTo(SchemaVariantsTest_TeamModel::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SchemaVariantsTest_CommentModel::class);
    }
}

class SchemaVariantsTest_UserModel extends Model {}

class SchemaVariantsTest_TeamModel extends Model {}

class SchemaVariantsTest_CommentModel extends Model {}

/**
 * @property SchemaVariantsTest_CommentModel $resource
 */
class SchemaVariantsTest_CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
class SchemaVariantsTest_CollectionWhenLoadedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comments' => SchemaVariantsTest_CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
class SchemaVariantsTest_WhenRelationLoadedCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comments' => $this->when(
                $this->resource->relationLoaded('comments'),
                fn (): AnonymousResourceCollection => SchemaVariantsTest_CommentResource::collection($this->buildComments()),
            ),
        ];
    }

    private function buildComments()
    {
        return $this->resource->comments;
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
class SchemaVariantsTest_BaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
            'team' => $this->whenLoaded('team'),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
#[SchemaVariant(name: 'AccountList', default: true)]
#[SchemaVariant(name: 'AccountWithUser', whenLoaded: ['user'])]
#[SchemaVariant(name: 'ExtendedAccount', whenLoaded: ['user', 'team'])]
class SchemaVariantsTest_VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
            'team' => $this->whenLoaded('team'),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
#[SchemaVariant(name: 'Account', whenLoaded: [])]
#[SchemaVariant(name: 'ExtendedAccount', whenLoaded: ['user', 'team'])]
class SchemaVariantsTest_NoDefaultVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
            'team' => $this->whenLoaded('team'),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
#[SchemaName('CustomAccount')]
class SchemaVariantsTest_NamedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
class SchemaVariantsTest_MergeWhenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->mergeWhen($this->relationLoaded('user'), [
                'profile' => $this->user->name,
            ]),
        ];
    }
}

/**
 * @property SchemaVariantsTest_PostModel $resource
 */
#[SchemaVariant(name: 'VariantA', whenLoaded: ['user'])]
#[SchemaVariant(name: 'VariantB', whenLoaded: ['user'])]
class SchemaVariantsTest_AmbiguousVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
        ];
    }
}

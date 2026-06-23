<?php

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Attributes\SchemaName;
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
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

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
        JsonResourceSchemaVariantsTest_BaseResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)
        ->toBe([
            'allOf' => [
                [
                    '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_BaseResource',
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'user' => ['type' => 'object'],
                    ],
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
        JsonResourceSchemaVariantsTest_BaseResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_BaseResource',
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
    $type = new Generic(JsonResourceSchemaVariantsTest_BaseResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_BaseResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_BaseResource',
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

it('uses fallback variant schema when relations unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_VariantResource::class, [new UnknownType]);

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
        JsonResourceSchemaVariantsTest_NoFallbackVariantResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, []),
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

it('loads correct variant based on matched relations priority', function () {
    $type = resourceWithModel(
        JsonResourceSchemaVariantsTest_PriorityVariantResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['user']),
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
#[JsonResourceSchemaVariant(name: 'Account', withLoaded: [])]
#[JsonResourceSchemaVariant(name: 'AccountWithUser', withLoaded: ['user'])]
#[JsonResourceSchemaVariant(name: 'ExtendedAccount', withLoaded: '*')]
class JsonResourceSchemaVariantsTest_PriorityVariantResource extends JsonResource
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
        JsonResourceSchemaVariantsTest_VariantResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['user', 'team']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/ExtendedAccount',
    ]);
});

it('uses fallback variant when relation state is unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/AccountList',
    ]);
});

it('uses base SchemaName when no fallback variant exists and relations are unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_NamedResource::class, [new UnknownType]);

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
        JsonResourceSchemaVariantsTest_MergeWhenResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_MergeWhenResource::class)
        ->toArray();

    expect($schema)->toBe([
        'allOf' => [
            [
                '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_MergeWhenResource',
            ],
            [
                'type' => 'object',
                'properties' => [
                    'profile' => ['type' => 'string'],
                ],
                'required' => ['profile'],
            ],
        ],
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
        ],
        'required' => ['id'],
    ]);
});

it('documents relation-conditioned resource collection when relation is loaded', function () {
    $type = resourceWithModel(
        JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['comments']),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        'allOf' => [
            [
                '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource',
            ],
            [
                'type' => 'object',
                'properties' => [
                    'comments' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CommentResource'],
                    ],
                ],
                'required' => ['comments'],
            ],
        ],
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('excludes relation-conditioned resource collection when relation is not loaded', function () {
    $type = resourceWithModel(
        JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, []),
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('falls back to optional resource collection field when relation state is unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    $componentSchema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource',
    ])->and($componentSchema)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'comments' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CommentResource'],
            ],
        ],
        'required' => ['id'],
    ]);
});

it('throws when multiple variants match with equal specificity', function () {
    $type = resourceWithModel(
        JsonResourceSchemaVariantsTest_AmbiguousVariantResource::class,
        modelWithRelations(JsonResourceSchemaVariantsTest_PostModel::class, ['user']),
    );

    $extension = makeJsonResourceExtension($this->context);

    expect(fn () => $extension->toSchema($type))
        ->toThrow(LogicException::class, 'Ambiguous JsonResourceSchemaVariant match');
});

class JsonResourceSchemaVariantsTest_PostModel extends Model
{
    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTo(JsonResourceSchemaVariantsTest_UserModel::class);
    }

    public function team()
    {
        return $this->belongsTo(JsonResourceSchemaVariantsTest_TeamModel::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(JsonResourceSchemaVariantsTest_CommentModel::class);
    }
}

class JsonResourceSchemaVariantsTest_UserModel extends Model {}

class JsonResourceSchemaVariantsTest_TeamModel extends Model {}

class JsonResourceSchemaVariantsTest_CommentModel extends Model {}

/**
 * @property JsonResourceSchemaVariantsTest_CommentModel $resource
 */
class JsonResourceSchemaVariantsTest_CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        ];
    }
}

/**
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
class JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comments' => JsonResourceSchemaVariantsTest_CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}

/**
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
class JsonResourceSchemaVariantsTest_BaseResource extends JsonResource
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
#[JsonResourceSchemaVariant(name: 'AccountList', fallback: true)]
#[JsonResourceSchemaVariant(name: 'AccountWithUser', withLoaded: ['user'])]
#[JsonResourceSchemaVariant(name: 'ExtendedAccount', withLoaded: ['user', 'team'])]
class JsonResourceSchemaVariantsTest_VariantResource extends JsonResource
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
#[JsonResourceSchemaVariant(name: 'Account', withLoaded: [])]
#[JsonResourceSchemaVariant(name: 'ExtendedAccount', withLoaded: ['user', 'team'])]
class JsonResourceSchemaVariantsTest_NoFallbackVariantResource extends JsonResource
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
#[SchemaName('CustomAccount')]
class JsonResourceSchemaVariantsTest_NamedResource extends JsonResource
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
class JsonResourceSchemaVariantsTest_MergeWhenResource extends JsonResource
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
 * @property JsonResourceSchemaVariantsTest_PostModel $resource
 */
#[JsonResourceSchemaVariant(name: 'VariantA', withLoaded: ['user'])]
#[JsonResourceSchemaVariant(name: 'VariantB', withLoaded: ['user'])]
class JsonResourceSchemaVariantsTest_AmbiguousVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user'),
        ];
    }
}

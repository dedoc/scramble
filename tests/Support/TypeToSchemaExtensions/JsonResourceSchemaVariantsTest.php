<?php

use Dedoc\Scramble\Attributes\JsonResourceSchemaVariant;
use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
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

function modelWithRelations(string $expression, string $resourceClass): Generic
{
    $modelType = getStatementType($expression);

    return new Generic($resourceClass, [$modelType]);
}

it('documents relation-conditioned properties as required when relation is loaded', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class."::query()->with('user')->first()",
        JsonResourceSchemaVariantsTest_BaseResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toHaveKey('allOf')
        ->and($schema['allOf'][1]['properties'])->toHaveKey('user')
        ->and($schema['allOf'][1]['required'] ?? [])->toContain('user');
});

it('excludes relation-conditioned properties when relation is not loaded', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class.'::query()->withoutEagerLoads()->first()',
        JsonResourceSchemaVariantsTest_BaseResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->not->toHaveKey('properties.user')
        ->and($schema)->not->toHaveKey('properties.team');
});

it('falls back to optional relation fields when relation state is unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_BaseResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toHaveKey('properties.user')
        ->and($schema['required'] ?? [])->not->toContain('user');
});

it('generates variant schema without relation-conditioned properties', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $extension->toSchema($type);

    expect($this->context->openApi->components->hasSchema('ParticipantList'))->toBeTrue();

    $variantSchema = $this->context->openApi->components->getSchema('ParticipantList')->type->toArray();

    expect($variantSchema)->not->toHaveKey('properties.user')
        ->and($variantSchema)->not->toHaveKey('properties.team')
        ->and($variantSchema['required'] ?? [])->toContain('id');
});

it('generates variant schema with loaded relations included and required', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $extension->toSchema($type);

    $variantSchema = $this->context->openApi->components->getSchema('ExtendedParticipant')->type->toArray();

    expect($variantSchema)->toHaveKey('properties.user')
        ->and($variantSchema)->toHaveKey('properties.team')
        ->and($variantSchema['required'] ?? [])->toContain('user')
        ->and($variantSchema['required'] ?? [])->toContain('team');
});

it('matches the most specific variant when relations are known', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class."::query()->withoutEagerLoads()->with('user', 'team')->first()",
        JsonResourceSchemaVariantsTest_VariantResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type);

    expect($schema)->toBeInstanceOf(Reference::class)
        ->and($schema->getUniqueName())->toBe('ExtendedParticipant');
});

it('uses fallback variant when relation state is unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_VariantResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type);

    expect($schema)->toBeInstanceOf(Reference::class)
        ->and($schema->getUniqueName())->toBe('ParticipantList');
});

it('uses base SchemaName when no fallback variant exists and relations are unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_NamedResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $reference = $extension->toSchema($type);

    expect($reference->getUniqueName())->toBe('CustomParticipant');
});

it('handles mergeWhen with relationLoaded', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class."::query()->withoutEagerLoads()->with('user')->first()",
        JsonResourceSchemaVariantsTest_MergeWhenResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toHaveKey('allOf')
        ->and($schema['allOf'][1]['properties'])->toHaveKey('profile')
        ->and($schema['allOf'][1]['required'] ?? [])->toContain('profile');
});

it('documents relation-conditioned resource collection when relation is loaded', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class."::query()->with('comments')->first()",
        JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->toHaveKey('allOf')
        ->and($schema['allOf'][1]['properties']['comments'])->toMatchArray([
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/JsonResourceSchemaVariantsTest_CommentResource'],
        ])
        ->and($schema['allOf'][1]['required'] ?? [])->toContain('comments');
});

it('excludes relation-conditioned resource collection when relation is not loaded', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class.'::query()->withoutEagerLoads()->first()',
        JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class,
    );

    $extension = makeJsonResourceExtension($this->context);
    $schema = $extension->toSchema($type)->toArray();

    expect($schema)->not->toHaveKey('properties.comments');
});

it('falls back to optional resource collection field when relation state is unknown', function () {
    $type = new Generic(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class, [new UnknownType]);

    $extension = makeJsonResourceExtension($this->context);
    $extension->toSchema($type);

    $schema = $this->context->openApi->components
        ->getSchema(JsonResourceSchemaVariantsTest_CollectionWhenLoadedResource::class)
        ->type
        ->toArray();

    expect($schema['properties'])->toHaveKey('comments')
        ->and($schema['required'] ?? [])->not->toContain('comments');
});

it('throws when multiple variants match with equal specificity', function () {
    $type = modelWithRelations(
        JsonResourceSchemaVariantsTest_PostModel::class."::query()->withoutEagerLoads()->with('user')->first()",
        JsonResourceSchemaVariantsTest_AmbiguousVariantResource::class,
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
#[JsonResourceSchemaVariant(name: 'ParticipantList', fallback: true)]
#[JsonResourceSchemaVariant(name: 'ParticipantWithUser', withLoaded: ['user'])]
#[JsonResourceSchemaVariant(name: 'ExtendedParticipant', withLoaded: ['user', 'team'])]
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
#[SchemaName('CustomParticipant')]
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

<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Http\Resources\Json\JsonResource;

use function Spatie\Snapshots\assertMatchesSnapshot;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
});

it('transforms simple types', function ($type, $openApiArrayed) {
    $transformer = app()->make(TypeTransformer::class, [
        'context' => $this->context,
    ]);

    expect(json_encode($transformer->transform($type)->toArray()))->toBe(json_encode($openApiArrayed));
})->with([
    [new IntegerType, ['type' => 'integer']],
    [new StringType, ['type' => 'string']],
    [new LiteralStringType('wow'), ['type' => 'string', 'example' => 'wow']],
    [new LiteralFloatType(157.50), ['type' => 'number', 'example' => 157.5]],
    [new BooleanType, ['type' => 'boolean']],
    [new MixedType, (object) []],
    [new ArrayType(value: new StringType), ['type' => 'array', 'items' => ['type' => 'string']]],
    [new KeyedArrayType([
        new ArrayItemType_('key', new IntegerType),
        new ArrayItemType_('optional_key', new IntegerType, true),
    ]), [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'integer'],
            'optional_key' => ['type' => 'integer'],
        ],
        'required' => ['key'],
    ]],
    [new KeyedArrayType([
        new ArrayItemType_(null, new IntegerType),
        new ArrayItemType_(null, new IntegerType),
        new ArrayItemType_(null, new IntegerType),
    ]), [
        'type' => 'array',
        'prefixItems' => [
            ['type' => 'integer'],
            ['type' => 'integer'],
            ['type' => 'integer'],
        ],
        'minItems' => 3,
        'maxItems' => 3,
        'additionalItems' => false,
    ]],
]);

it('gets json resource type', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(ComplexTypeHandlersTest_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets enum with values type', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(StatusTwo::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets enum with values type and description', function () {
    config()->set('scramble.enum_cases_description_strategy', 'description');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(StatusThree::class);

    expect($extension->toSchema($type)->toArray()['description'])
        ->toBe(<<<'EOF'
| |
|---|
| `draft` <br/> Drafts are the posts that are not visible by visitors. |
| `published` <br/> Published posts are visible to visitors. |
| `archived` <br/> Archived posts are not visible to visitors. |
EOF);
});

it('gets enum with values type and description with extensions', function () {
    config()->set('scramble.enum_cases_description_strategy', 'extension');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(StatusThree::class);

    expect($extension->toSchema($type)->toArray()['x-enumDescriptions'])
        ->toBe([
            'draft' => 'Drafts are the posts that are not visible by visitors.',
            'published' => 'Published posts are visible to visitors.',
            'archived' => 'Archived posts are not visible to visitors.',
        ]);
});

it('gets enum with values type and description without cases', function () {
    config()->set('scramble.enum_cases_description_strategy', false);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(StatusThree::class);

    expect($extension->toSchema($type)->toArray())
        ->toBe([
            'type' => 'string',
            'enum' => ['draft', 'published', 'archived'],
        ]);
});

it('gets json resource type with nested merges', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(ComplexTypeHandlersWithNestedTest_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type with when', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(ComplexTypeHandlersWithWhen_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type with when loaded', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
        AnonymousResourceCollectionTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(ComplexTypeHandlersWithWhenLoaded_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type with when counted', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [
        JsonResourceTypeToSchema::class,
        AnonymousResourceCollectionTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $this->context->openApi->components, $this->context);

    $type = new ObjectType(ComplexTypeHandlersWithWhenCounted_SampleType::class);

    assertMatchesSnapshot($extension->toSchema($type)->toArray());
});

it('gets json resource type reference', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ComplexTypeHandlersTest_SampleType::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/ComplexTypeHandlersTest_SampleType',
    ]);

    assertMatchesSnapshot($this->context->openApi->components->getSchema(ComplexTypeHandlersTest_SampleType::class)->toArray());
});

it('gets nullable type reference', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new Union([
        new ObjectType(ComplexTypeHandlersTest_SampleType::class),
        new NullType,
    ]);

    expect($transformer->transform($type)->toArray())->toBe([
        'anyOf' => [
            ['$ref' => '#/components/schemas/ComplexTypeHandlersTest_SampleType'],
            ['type' => 'null'],
        ],
    ]);
});

it('infers date column directly referenced in json as date-time', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(InferTypesTest_JsonResourceWithCarbonAttribute::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/InferTypesTest_JsonResourceWithCarbonAttribute',
    ]);

    expect($this->context->openApi->components->getSchema(InferTypesTest_JsonResourceWithCarbonAttribute::class)->toArray()['properties']['created_at'])->toBe([
        'type' => ['string', 'null'],
        'format' => 'date-time',
    ]);
});

it('supports @example tag in api resource', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ApiResourceTest_ResourceWithExamples::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/ApiResourceTest_ResourceWithExamples',
    ]);

    expect($this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithExamples::class)->toArray()['properties']['id'])->toBe([
        'type' => 'integer',
        'examples' => [
            'Foo',
            'Multiword example',
        ],
    ]);
});

it('supports @format tag in api resource', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ApiResourceTest_ResourceWithFormat::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/ApiResourceTest_ResourceWithFormat',
    ]);

    expect($this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithFormat::class)->toArray()['properties']['now'])->toBe([
        'type' => 'string',
        'format' => 'date-time',
    ]);
});

it('supports simple comments descriptions in api resource', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ApiResourceTest_ResourceWithSimpleDescription::class);

    expect($transformer->transform($type)->toArray())->toBe([
        '$ref' => '#/components/schemas/ApiResourceTest_ResourceWithSimpleDescription',
    ]);

    expect($this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithSimpleDescription::class)->toArray()['properties']['now'])->toBe([
        'type' => 'string',
        'description' => 'The date of the current moment.',
    ]);
    expect($this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithSimpleDescription::class)->toArray()['properties']['now2'])->toBe([
        'type' => 'string',
        'description' => 'Inline comments are also supported.',
    ]);
});

class ComplexTypeHandlersTest_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => 1,
            $this->mergeWhen(true, [
                'hey' => 'ho',
            ]),
            $this->merge([
                'bar' => 'foo',
            ]),
        ];
    }
}

class ComplexTypeHandlersWithNestedTest_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => 1,
            'wait' => [
                'one' => 1,
                $this->merge([
                    'bar' => 'foo',
                    'kek' => [
                        $this->merge([
                            'bar' => 'foo',
                        ]),
                    ],
                ]),
            ],
            $this->mergeWhen(true, [
                'hey' => 'ho',
            ]),
            $this->merge([
                'bar' => 'foo',
            ]),
        ];
    }
}

class ComplexTypeHandlersWithWhen_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => $this->when(true, fn () => 1),
            'bar' => $this->when(true, fn () => 'b', null),
        ];
    }
}

class ComplexTypeHandlersWithWhenLoaded_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'opt_foo_new' => new ComplexTypeHandlersWithWhen_SampleType($this->whenLoaded('foo')),
            'opt_foo_make' => ComplexTypeHandlersWithWhen_SampleType::make($this->whenLoaded('foo')),
            'opt_foo_collection' => ComplexTypeHandlersWithWhen_SampleType::collection($this->whenLoaded('foo')),
            'foo_new' => new ComplexTypeHandlersWithWhen_SampleType($this->foo),
            'foo_make' => ComplexTypeHandlersWithWhen_SampleType::make($this->foo),
            'foo_collection' => ComplexTypeHandlersWithWhen_SampleType::collection($this->foo),
            'bar' => $this->whenLoaded('bar', fn () => 1),
            'bar_nullable' => $this->whenLoaded('bar', fn () => 's', null),
        ];
    }
}

class ComplexTypeHandlersWithWhenCounted_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'bar_single' => $this->whenCounted('bar'),
            'bar_fake_count' => $this->whenCounted('bar', 1),
            'bar_different_literal_types' => $this->whenCounted('bar', fn () => 1, 5),
            'bar_identical_literal_types' => $this->whenCounted('bar', fn () => 1, 1),
            'bar_string' => $this->whenCounted('bar', fn () => '2'),
            'bar_int' => $this->whenCounted('bar', fn () => 1),
            'bar_useless' => $this->whenCounted('bar', null),
            'bar_nullable' => $this->whenCounted('bar', fn () => 3, null),
        ];
    }
}

/**
 * @property SamplePostModel $resource
 */
class InferTypesTest_JsonResourceWithCarbonAttribute extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

/**
 * @property SamplePostModel $resource
 */
class ApiResourceTest_ResourceWithExamples extends JsonResource
{
    public function toArray($request)
    {
        return [
            /**
             * @example Foo
             * @example Multiword example
             */
            'id' => $this->id,
        ];
    }
}

/**
 * @property SamplePostModel $resource
 */
class ApiResourceTest_ResourceWithFormat extends JsonResource
{
    public function toArray($request)
    {
        return [
            /**
             * @var string $now
             *
             * @format date-time
             */
            'now' => now(),
        ];
    }
}

class ApiResourceTest_ResourceWithSimpleDescription extends JsonResource
{
    public function toArray($request)
    {
        return [
            /**
             * The date of the current moment.
             */
            'now' => now(),
            // Inline comments are also supported.
            'now2' => now(),
        ];
    }
}

enum StatusTwo: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

enum StatusThree: string
{
    /**
     * Drafts are the posts that are not visible by visitors.
     */
    case DRAFT = 'draft';
    /**
     * Published posts are visible to visitors.
     */
    case PUBLISHED = 'published';
    /**
     * Archived posts are not visible to visitors.
     */
    case ARCHIVED = 'archived';
}

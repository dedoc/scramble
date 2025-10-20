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
    [new LiteralStringType('wow'), ['type' => 'string', 'enum' => ['wow']]],
    [new LiteralFloatType(157.50), ['type' => 'number', 'enum' => [157.5]]],
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

it('gets enum with its description and cases description (#922)', function () {
    config()->set('scramble.enum_cases_description_strategy', 'description');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(StatusFour::class);

    expect($extension->toSchema($type)->toArray()['description'])
        ->toBe(<<<'EOF'
Description for StatusFour.
| |
|---|
| `draft` <br/> Drafts are the posts that are not visible by visitors. |
EOF);
});

it('preserves enum cases description but overrides the enum schema description when used in object (#922)', function () {
    config()->set('scramble.enum_cases_description_strategy', 'description');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);

    $type = getStatementType(<<<'EOF'
[
    /**
     * Override for StatusFour.
     * @var StatusFour
     */
    'a' => unknown(),
]
EOF);

    expect($transformer->transform($type)->toArray()['properties']['a']['description'])
        ->toBe(<<<'EOF'
Override for StatusFour.
| |
|---|
| `draft` <br/> Drafts are the posts that are not visible by visitors. |
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

it('gets enum with values type and names as varnames with extensions', function () {
    config()->set('scramble.enum_cases_names_strategy', 'varnames');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(InvalidEnumValues::class);

    expect($extension->toSchema($type)->toArray()['x-enum-varnames'])
        ->toBe([
            'PLUS',
            'MINUS',
            'ONE',
        ]);
});

it('gets enum with values type without names with extensions', function () {
    config()->set('scramble.enum_cases_names_strategy', false);

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(InvalidEnumValues::class);

    expect(array_keys($extension->toSchema($type)->toArray()))
        ->not()
        ->toContain('x-enumNames', 'x-enum-varnames');
});

it('gets enum with values type and names as enumNames with extensions', function () {
    config()->set('scramble.enum_cases_names_strategy', 'names');

    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [EnumToSchema::class]);
    $extension = new EnumToSchema($infer, $transformer, $this->context->openApi->components);

    $type = new ObjectType(InvalidEnumValues::class);

    expect($extension->toSchema($type)->toArray()['x-enumNames'])
        ->toBe([
            'PLUS',
            'MINUS',
            'ONE',
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

    $schema = $this->context->openApi->components->getSchema(InferTypesTest_JsonResourceWithCarbonAttribute::class)->toArray();

    expect($schema['properties']['created_at'])->toBe([
        'type' => ['string', 'null'],
        'format' => 'date-time',
    ]);

    expect($schema['properties']['deleted_at'])->toBe([
        'type' => 'string',
        'format' => 'date-time',
    ]);

    expect($schema['required'])->toBe([
        'id',
        'created_at',
        'updated_at',
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
        'format' => 'date-time',
        'description' => 'The date of the current moment.',
    ]);
    expect($this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithSimpleDescription::class)->toArray()['properties']['now2'])->toBe([
        'type' => 'string',
        'format' => 'date-time',
        'description' => 'Inline comments are also supported.',
    ]);
});

it('supports list types', function () {
    $transformer = new TypeTransformer($infer = app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ApiResourceTest_ResourceWithList::class);

    $schema = $transformer->transform($type)->toArray();
    $component = $this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithList::class)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/ApiResourceTest_ResourceWithList',
    ]);

    expect($component)
        ->toBe([
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'required' => ['items'],
        ]);
});

it('supports integers', function () {
    $transformer = new TypeTransformer(app(Infer::class), $this->context, [JsonResourceTypeToSchema::class]);

    $type = new ObjectType(ApiResourceTest_ResourceWithIntegers::class);

    $schema = $transformer->transform($type)->toArray();
    $component = $this->context->openApi->components->getSchema(ApiResourceTest_ResourceWithIntegers::class)->toArray();

    expect($schema)->toBe([
        '$ref' => '#/components/schemas/ApiResourceTest_ResourceWithIntegers',
    ]);

    expect($component)
        ->toBe([
            'type' => 'object',
            'properties' => [
                'zero' => [
                    'type' => 'integer',
                ],
                'one' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'minus_one' => [
                    'type' => 'integer',
                    'maximum' => -1,
                ],
                'minus_two' => [
                    'type' => 'integer',
                    'maximum' => 0,
                ],
                'two' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
                'three' => [
                    'type' => 'integer',
                ],
                'four' => [
                    'type' => 'integer',
                    'minimum' => 4,
                    'maximum' => 5,
                ],
                'four_max' => [
                    'type' => 'integer',
                    'minimum' => 4,
                ],
                'min_to_five' => [
                    'type' => 'integer',
                    'maximum' => 5,
                ],
                'max_to_five' => [
                    'type' => 'integer',
                ],
                'five_to_min' => [
                    'type' => 'integer',
                ],
            ],
            'required' => [
                'zero',
                'one',
                'minus_one',
                'minus_two',
                'two',
                'three',
                'four',
                'four_max',
                'min_to_five',
                'max_to_five',
                'five_to_min',
            ],
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
            'deleted_at' => $this->whenNotNull($this->deleted_at),
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

class ApiResourceTest_ResourceWithList extends JsonResource
{
    public function toArray($request)
    {
        return [
            /** @var list<int> $items */
            'items' => $this->resource->items,
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

class ApiResourceTest_ResourceWithIntegers extends JsonResource
{
    public function toArray($request)
    {
        return [
            /** @var int */
            'zero' => 0,
            /** @var positive-int */
            'one' => 1,
            /** @var negative-int */
            'minus_one' => -1,
            /** @var non-positive-int */
            'minus_two' => -2,
            /** @var non-negative-int */
            'two' => 2,
            /** @var non-zero-int */
            'three' => 3,
            /** @var int<4, 5> */
            'four' => 4,
            /** @var int<4, max> */
            'four_max' => 4,
            /** @var int<min, 5> */
            'min_to_five' => 4,
            /** @var int<max, 5> */
            'max_to_five' => 4,
            /** @var int<5, min> */
            'five_to_min' => 4,
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

/**
 * Description for StatusFour.
 */
enum StatusFour: string
{
    /**
     * Drafts are the posts that are not visible by visitors.
     */
    case DRAFT = 'draft';
}

enum InvalidEnumValues: string
{
    case PLUS = '+';
    case MINUS = '-';
    case ONE = '1';
}

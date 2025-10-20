<?php

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

use function Spatie\Snapshots\assertMatchesSnapshot;

beforeEach(function () {
    $this->openApiTransformer = $openApiTransformer = app()->make(TypeTransformer::class, [
        'context' => new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig),
    ]);
    $this->buildRulesToParameters = function (array $rules) use ($openApiTransformer): RulesToParameters {
        return new RulesToParameters($rules, [], $openApiTransformer);
    };
});

function validationRulesToDocumentationWithDeep($rulesToParameters)
{
    return (new DeepParametersMerger(collect($rulesToParameters->handle())))
        ->handle();
}

// @todo: move rules from here to Generator/Request/ValidationRulesDocumentation test
it('supports present rule', function () {
    $rules = [
        'password' => ['present'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'name' => 'password',
            'in' => 'query',
            'required' => true,
            'schema' => ['type' => 'string'],
        ]);
});

it('supports present rule on array', function () {
    $rules = [
        'users' => ['present', 'array'],
        'users.*' => ['string'],
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'name' => 'users',
            'in' => 'query',
            'schema' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'required' => true,
        ]);
});

it('supports present rule in array', function () {
    $rules = [
        'user.password' => ['present'],
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'name' => 'user',
            'in' => 'query',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'password' => ['type' => 'string'],
                ],
                'required' => ['password'],
            ],
        ]);
});

it('supports confirmed rule', function () {
    $rules = [
        'password' => ['required', 'min:8', 'confirmed'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(2)
        ->and($params[1])
        ->toMatchArray(['name' => 'password_confirmation']);
});

it('supports Rule::when', function () {
    $rules = [
        'password' => [Rule::when(0, ['boolean'])],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'schema' => [
                'type' => 'boolean',
            ],
        ]);
});

it('supports Rule::when with one required case', function () {
    $rules = [
        'foo' => Rule::when(0, ['boolean', 'required']),
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'name' => 'foo',
            'in' => 'query',
            'schema' => [
                'type' => 'boolean',
            ],
        ]);
});

it('supports Rule::when with all cases', function () {
    $rules = [
        'foo' => [
            'required',
            'string',
            Rule::when(0, ['min:1'], ['min:3']),
            Rule::when(0, ['max:4'], ['max:15']),
        ],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'name' => 'foo',
            'in' => 'query',
            'required' => true,
            'schema' => [
                'anyOf' => [
                    [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 4,
                    ],
                    [
                        'type' => 'string',
                        'minLength' => 3,
                        'maxLength' => 4,
                    ],
                    [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 15,
                    ],
                    [
                        'type' => 'string',
                        'minLength' => 3,
                        'maxLength' => 15,
                    ],
                ],
            ],
        ]);
});

it('supports confirmed rule in array', function () {
    $rules = [
        'user.password' => ['required', 'min:8', 'confirmed'],
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'password' => ['type' => 'string', 'minLength' => 8],
                    'password_confirmation' => ['type' => 'string', 'minLength' => 8],
                ],
                'required' => ['password', 'password_confirmation'],
            ],
        ]);
});

it('supports sometimes rule before required', function () {
    $rules = [
        'user.param1' => ['sometimes', 'required', 'min:8'],
        'user.param2' => ['required', 'min:8'],
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string', 'minLength' => 8],
                    'param2' => ['type' => 'string', 'minLength' => 8],
                ],
                'required' => ['param2'],
            ],
        ]);
});

it('supports multiple confirmed rule', function () {
    $rules = [
        'password' => ['required', 'min:8', 'confirmed'],
        'email' => ['required', 'email', 'confirmed'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(4)
        ->and($params[2])
        ->toMatchArray(['name' => 'password_confirmation'])
        ->and($params[3])
        ->toMatchArray(['name' => 'email_confirmation']);
});

it('works when last validation item is items array', function () {
    $rules = [
        'items.*.name' => 'required|string',
        'items.*.email' => 'email',
        'items.*' => 'array',
        'items' => ['array', 'min:1', 'max:10'],
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    expect($params = collect($params)->map->toArray()->all())
        ->toBe([
            [
                'name' => 'items',
                'in' => 'query',
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                            ],
                            'email' => [
                                'type' => 'string',
                                'format' => 'email',
                            ],
                        ],
                        'required' => [
                            0 => 'name',
                        ],
                    ],
                    'minItems' => 1.0,
                    'maxItems' => 10.0,
                ],
            ],
        ]);
});

it('extract rules from array like rules', function () {
    $rules = [
        'id' => 'int',
        'some' => 'required|array',
        'some.*.id' => 'required|int',
        'some.*.name' => 'string',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extract rules from array rules', function () {
    $rules = [
        'foo' => 'array',
        'foo.id' => 'int',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extract rules from array rules with both wildcard and specific props', function () {
    $rules = [
        'foo' => 'array:a,b,c,d',
        'foo.*' => 'int',
        'foo.a' => 'boolean',
        'foo.b' => 'array:x,y,z',
        'other' => 'int',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    $fooParams = collect($params)->keyBy('name')->get('foo');
    $fooProperties = $fooParams->schema->toArray()['properties'];

    $otherParams = collect($params)->keyBy('name')->get('other');

    expect($fooProperties)->toHaveCount(4)
        ->and($fooProperties['a']['type'])->toBe('boolean')
        ->and($fooProperties['b']['type'])->toBe('object')
        ->and($fooProperties['c']['type'])->toBe('integer')
        ->and($fooProperties['d']['type'])->toBe('integer')
        ->and($otherParams->schema->toArray()['type'])->toBe('integer');
});

it('supports array rule details', function () {
    $rules = [
        'destination' => 'array:lat,lon|required',
        'destination.lat' => 'numeric|required|min:54|max:60',
        'destination.lon' => 'numeric|required|min:20|max:28.5',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(json_encode(collect($params)->map->toArray()->all()));
});

it('supports array rule params', function () {
    $rules = [
        'destination' => 'array:lat,lon|required',
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extract rules from enum rule', function () {
    $rules = [
        'status' => new Enum(StatusValidationEnum::class),
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

if (method_exists(Enum::class, 'only')) {
    it('extract rules from enum rule with only', function () {
        $rules = [
            'status' => (new Enum(StatusValidationEnum::class))->only([StatusValidationEnum::DRAFT, StatusValidationEnum::ARCHIVED]),
        ];

        $params = ($this->buildRulesToParameters)($rules)->handle();

        expect($params[0]->toArray()['schema'])->toBe([
            'type' => 'string',
            'enum' => [
                'draft',
                'archived',
            ],
        ]);
    });

    it('extract rules from enum rule with except', function () {
        $rules = [
            'status' => (new Enum(StatusValidationEnum::class))->except(StatusValidationEnum::DRAFT),
        ];

        $params = ($this->buildRulesToParameters)($rules)->handle();

        expect($params[0]->toArray()['schema'])->toBe([
            'type' => 'string',
            'enum' => [
                'published',
                'archived',
            ],
        ]);
    });
}

it('extract rules from object like rules', function () {
    $rules = [
        'channels.agency' => 'nullable',
        'channels.agency.id' => 'nullable|int',
        'channels.agency.name' => 'nullable|string',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('supports uuid', function () {
    $rules = [
        'foo' => ['required', 'uuid', 'exists:App\Models\Section,id'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extract rules from object like rules heavy case', function () {
    $rules = [
        'channel' => 'required',
        'channel.publisher' => 'nullable|array',
        'channels.publisher.id' => 'nullable|int',
        'channels.publisher.name' => 'nullable|string',
        'channels.channel_url' => 'filled|url',
        'channels.agency' => 'nullable',
        'channels.agency.id' => 'nullable|int',
        'channels.agency.name' => 'nullable|string',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extract rules from object like rules with explicit array', function () {
    $rules = [
        'channels.publisher' => 'array',
        'channels.publisher.id' => 'int',
    ];

    $params = validationRulesToDocumentationWithDeep(($this->buildRulesToParameters)($rules));

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('supports between rule', function () {
    $rules = [
        'foo' => 'between:36,42',
    ];

    $type = ($this->buildRulesToParameters)($rules)->handle()[0]->schema->type;

    expect($type->min)->toBe(36.0)
        ->and($type->max)->toBe(42.0);
});

it('supports exists rule', function () {
    $rules = [
        'email' => 'required|email|exists:users,email',
    ];

    $type = ($this->buildRulesToParameters)($rules)->handle()[0]->schema->type;

    expect($type)->toBeInstanceOf(StringType::class)
        ->and($type->format)->toBe('email');
});

it('supports image rule', function () {
    $rules = [
        'image' => 'required|image',
    ];

    $type = ($this->buildRulesToParameters)($rules)->handle()[0]->schema->type;

    expect($type)->toBeInstanceOf(StringType::class)
        ->and($type->contentMediaType)->toBe('application/octet-stream');
});

it('supports file rule', function () {
    $rules = [
        'file' => 'required|file',
    ];

    $type = ($this->buildRulesToParameters)($rules)->handle()[0]->schema->type;

    expect($type)->toBeInstanceOf(StringType::class)
        ->and($type->contentMediaType)->toBe('application/octet-stream');
});

it('converts min rule into "minimum" for numeric fields', function () {
    $rules = [
        'num' => ['int', 'min:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\NumberType::class)
        ->toHaveKey('minimum', 8);
});

it('converts max rule into "maximum" for numeric fields', function () {
    $rules = [
        'num' => ['int', 'max:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\NumberType::class)
        ->toHaveKey('maximum', 8);
});

it('converts min rule into "minLength" for string fields', function () {
    $rules = [
        'str' => ['string', 'min:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveKey('minLength', 8);
});

it('converts max rule into "maxLength" for string fields', function () {
    $rules = [
        'str' => ['string', 'max:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveKey('maxLength', 8);
});

it('converts min rule into "minItems" for array fields', function () {
    $rules = [
        'num' => ['array', 'min:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\ArrayType::class)
        ->toHaveKey('minItems', 8);
});

it('converts max rule into "maxItems" for array fields', function () {
    $rules = [
        'num' => ['array', 'max:8'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\ArrayType::class)
        ->toHaveKey('maxItems', 8);
});

it('documents nullable uri rule', function () {
    $rules = [
        'page_url' => ['nullable', 'url'],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', 'uri')
        ->toHaveProperty('nullable', true);
});

it('documents required enum rule', function () {
    $rules = [
        'pizza.status' => ['required',  Rule::enum(StatusValidationEnum::class)],
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params[0]->schema->type->required)
        ->toBe(['status']);
});

it('documents root arrays', function () {
    $rules = [
        '*.parentId' => 'required|string',
        '*.childId' => 'string',
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect(($type = $params[0]->schema->type->toArray()))
        ->toBe([
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'parentId' => [
                        'type' => 'string',
                    ],
                    'childId' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'parentId',
                ],
            ],
        ]);
});

it('documents date rule', function () {
    $rules = [
        'some_date' => 'date',
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', 'date-time');
});

it('documents date rule with Y-m-d format', function () {
    $rules = [
        'some_date' => 'date:Y-m-d',
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(1)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', 'date');
});

it('documents date_format rule with format', function () {
    $rules = [
        'some_date' => 'date_format:Y-m-d',
        'some_date_time' => 'date_format:Y-m-d H:i:s',
        'some_time' => 'date_format:H:i',
    ];

    $params = ($this->buildRulesToParameters)($rules)->handle();

    expect($params = collect($params)->all())
        ->toHaveCount(3)
        ->and($params[0]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', 'date')
        ->and($params[1]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', 'date-time')
        ->and($params[2]->schema->type)
        ->toBeInstanceOf(\Dedoc\Scramble\Support\Generator\Types\StringType::class)
        ->toHaveProperty('format', '');
});

it('supports prohibited if rule evaluation', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', ProhibitedIf_ValidationRulesDocumentingTest::class));

    expect($openApiDocument['paths']['/test']['get']['parameters'][0])->toBe([
        'name' => 'foo',
        'in' => 'query',
        'schema' => ['type' => 'string'],
    ]);
});
class ProhibitedIf_ValidationRulesDocumentingTest
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'foo' => [
                Rule::prohibitedIf(
                    fn (): bool => ! $this->user()->can('create', Some::class)
                ),
            ],
        ]);
    }
}

it('extracts rules from request->validate call', function () {
    RouteFacade::get('api/test', [ValidationRulesDocumenting_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('extracts rules docs', function () {
    RouteFacade::get('api/test', [ValidationRulesWithDocs_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument['paths']['/test']['get']['parameters']);
});

it('extracts rules docs from form request', function () {
    RouteFacade::get('api/test', [ValidationRulesWithDocsAndFormRequest_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument['paths']['/test']['get']['parameters']);
});

it('extracts rules docs when using consts in form request', function ($action) {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', $action));

    expect($openApiDocument['paths']['/test']['get']['parameters'][0])->toBe([
        'name' => 'foo',
        'in' => 'query',
        'required' => true,
        'description' => 'A foo prop.',
        'schema' => ['type' => 'string'],
        'example' => 'wow',
    ]);
})->with([
    [[FormRequestsValidationRulesWithConstsDocs_Test::class, 'index']],
    [[FormRequestsValidationRulesWithConstsDocs_Test::class, '_static']],
    [[FormRequestsValidationRulesWithConstsDocs_Test::class, '_self']],
]);

it('extracts rules from Validator::make facade call', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ValidationFacadeRulesDocumenting_Test::class, 'index']);
    });

    assertMatchesSnapshot($openApiDocument);
});

it('supports validation rules and form request at the same time', function () {
    RouteFacade::get('api/test', [ValidationRulesAndFormRequestAtTheSameTime_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class ValidationRulesDocumenting_Test
{
    /**
     * @return ValidationRulesDocumenting_TestResource
     */
    public function index(Request $request)
    {
        $request->validate([
            'content' => ['required', Rule::in('wow')],
        ]);

        return new ValidationRulesDocumenting_TestResource(12);
    }
}

class ValidationFacadeRulesDocumenting_Test
{
    public function index(Request $request)
    {
        Validator::make($request->all(), [
            'content' => ['required', Rule::in('wow')],
        ], attributes: []);
    }
}

class ValidationRulesDocumenting_TestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => 1,
        ];
    }
}

class ValidationRulesAndFormRequestAtTheSameTime_Test
{
    public function index(ValidationRulesAndFormRequestAtTheSameTime_TestFormRequest $request)
    {
        $request->validate([
            'from_validate_call' => ['required', 'string'],
        ]);
    }
}

class ValidationRulesWithDocs_Test
{
    public function index(Request $request)
    {
        $request->validate([
            /**
             * A foo prop.
             *
             * @example wow
             */
            'foo' => ['required', 'string'],
            // A bar prop.
            'bar' => 'string',
            /**
             * A type redefined prop.
             *
             * @var int
             */
            'var' => ['required', 'string'],
        ]);
    }
}

class ValidationRulesWithDocsAndFormRequest_Test
{
    public function index(FormRequestWithDocs_TestFormRequest $request)
    {
        $request->validate([
            /**
             * A foo prop.
             *
             * @example wow
             */
            'foo' => ['required', 'string'],
        ]);
    }
}

class FormRequestsValidationRulesWithConstsDocs_Test
{
    const TEST = 'foo';

    public function index(Request $request)
    {
        $request->validate([
            /**
             * A foo prop.
             *
             * @example wow
             */
            FormRequestsValidationRulesWithConstsDocs_Test::TEST => ['required', 'string'],
        ]);
    }

    public function _static(Request $request)
    {
        $request->validate([
            /**
             * A foo prop.
             *
             * @example wow
             */
            static::TEST => ['required', 'string'],
        ]);
    }

    public function _self(Request $request)
    {
        $request->validate([
            /**
             * A foo prop.
             *
             * @example wow
             */
            self::TEST => ['required', 'string'],
        ]);
    }
}

class ValidationRulesAndFormRequestAtTheSameTime_TestFormRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'from_form_request' => 'int',
        ];
    }
}

class FormRequestWithDocs_TestFormRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            // Wow, this is a comment!
            'from_form_request' => 'int',
        ];
    }
}

enum StatusValidationEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

it('supports manual authentication info', function () {
    RouteFacade::get('api/test', [ControllerWithoutSecurity::class, 'index']);
    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');

    Scramble::extendOpenApi(function (OpenApi $openApi) {
        $openApi->secure(
            ApiKeySecurityScheme::apiKey('query', 'api_token')
        );
    });
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class ControllerWithoutSecurity
{
    /**
     * @unauthenticated
     */
    public function index() {}
}

it('extracts manual documentation for rules from request->validate call when rules are defined in a different method', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', ValidateCallDifferentMethodsRules_ValidationRulesDocumentingTest::class));

    expect($openApiDocument['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'foo',
            'in' => 'query',
            'required' => true,
            'description' => 'Nice parameter',
            'schema' => ['type' => 'string'],
        ]);
});
class ValidateCallDifferentMethodsRules_ValidationRulesDocumentingTest
{
    public function __invoke(Request $request)
    {
        $request->validate((new ValidateCallDifferentMethodsRules_ValidationRulesDocumentingTest)->getRules());
    }

    public function getRules()
    {
        return [
            // Nice parameter
            'foo' => ['required', 'string'],
        ];
    }
}

it('extracts manual documentation for merged rules from request->validate call when rules are defined in a different method', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', ValidateCallMergedRules_ValidationRulesDocumentingTest::class));

    expect($openApiDocument['paths']['/test']['get']['parameters'])
        ->toBe([
            [
                'name' => 'foo',
                'in' => 'query',
                'required' => true,
                'description' => 'Nice parameter',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'bar',
                'in' => 'query',
                'description' => 'Great parameter',
                'schema' => ['type' => 'integer'],
            ],
        ]);
});
class ValidateCallMergedRules_ValidationRulesDocumentingTest
{
    public function __invoke(Request $request)
    {
        $request->validate(array_merge(
            (new ValidateCallMergedRules_ValidationRulesDocumentingTest)->getFooRules(),
            (new ValidateCallMergedRules_ValidationRulesDocumentingTest)->getBarRules(),
        ));
    }

    public function getFooRules()
    {
        return [
            // Nice parameter
            'foo' => ['required', 'string'],
        ];
    }

    public function getBarRules()
    {
        return [
            // Great parameter
            'bar' => ['integer'],
        ];
    }
}

it('extracts manual documentation for rules in form request', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', FormRequestRulesController_ValidationRulesDocumentingTest::class));

    expect($openApiDocument['paths']['/test']['get']['parameters'])
        ->toBe([
            [
                'name' => 'foo',
                'in' => 'query',
                'required' => true,
                'description' => 'Nice parameter',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'bar',
                'in' => 'query',
                'description' => 'Great parameter',
                'schema' => ['type' => 'integer'],
            ],
        ]);
});
class FormRequestRulesController_ValidationRulesDocumentingTest
{
    public function __invoke(FormRequestRulesRequest_ValidationRulesDocumentingTest $request) {}
}
class FormRequestRulesRequest_ValidationRulesDocumentingTest
{
    public function rules()
    {
        $rules = [
            // Nice parameter
            'foo' => ['required', 'string'],
        ];

        return array_merge($rules, $this->getBarRules());
    }

    public function getBarRules()
    {
        return [
            // Great parameter
            'bar' => ['integer'],
        ];
    }
}

it('extracts rules when the action is used by few routes', function () {
    $routes = [
        RouteFacade::get('api/a', ValidateCallSameActionDifferentRoutes_ValidationRulesDocumentingTest::class),
        RouteFacade::get('api/b', ValidateCallSameActionDifferentRoutes_ValidationRulesDocumentingTest::class),
    ];

    Scramble::routes(fn (Route $r) => in_array($r, $routes, strict: true));
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    $expectedParameters = [[
        'name' => 'foo',
        'in' => 'query',
        'required' => true,
        'description' => 'Nice parameter',
        'schema' => ['type' => 'string'],
    ]];

    expect($openApiDocument['paths']['/a']['get']['parameters'])
        ->toBe($expectedParameters)
        ->and($openApiDocument['paths']['/b']['get']['parameters'])
        ->toBe($expectedParameters);
});
class ValidateCallSameActionDifferentRoutes_ValidationRulesDocumentingTest
{
    public function __invoke(Request $request)
    {
        $request->validate([
            // Nice parameter
            'foo' => ['required', 'string'],
        ]);
    }
}

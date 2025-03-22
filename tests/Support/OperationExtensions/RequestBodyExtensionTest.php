<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('doesnt add body when empty', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__doesnt_use_body_when_empty::class, 'store']);
    });

    expect($openApiDocument['paths']['/test']['post'])
        ->not->toHaveKey('requestBody');
});
class RequestBodyExtensionTest__doesnt_use_body_when_empty
{
    public function store(Illuminate\Http\Request $request) {}
}

it('uses application/json media type as a default request media type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__uses_application_json_as_default::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content'])
        ->toHaveKey('application/json')
        ->toHaveLength(1);
});
class RequestBodyExtensionTest__uses_application_json_as_default
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('generates request body only for certain http methods', function (string $method, bool $isRequestBodyExpected) {
    $openApiDocument = generateForRoute(function () use ($method) {
        return RouteFacade::addRoute($method, 'api/test', [RequestBodyExtensionTest__generates_request_body_only_for_certain_http_methods::class, 'index']);
    });

    expect($openApiDocument['paths']['/test'][$method])
        ->toHaveKeys($isRequestBodyExpected ? ['requestBody'] : ['parameters'])
        ->and($openApiDocument['paths']['/test'][$method])
        ->not()->toHaveKeys($isRequestBodyExpected ? ['parameters'] : ['requestBody']);
})->with([
    ['post', true],
    ['put', true],
    ['patch', true],
    ['get', false],
    ['head', false],
    ['delete', false],
]);
class RequestBodyExtensionTest__generates_request_body_only_for_certain_http_methods
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('allows manually defining a request media type', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__allows_manual_request_media_type::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content'])
        ->toHaveKey('application/xml')
        ->toHaveLength(1);
});
class RequestBodyExtensionTest__allows_manual_request_media_type
{
    /**
     * @requestMediaType application/xml
     */
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('automatically infers multipart/form-data as request media type when some of body params is binary', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__automaticall_infers_form_data::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content'])
        ->toHaveKey('multipart/form-data')
        ->toHaveLength(1);
});
class RequestBodyExtensionTest__automaticall_infers_form_data
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'file']);
    }
}

it('adds array body', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', RequestBodyExtensionTest__array_body::class);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe([
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'foo' => [
                        'type' => 'string',
                    ],
                    'bar' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['foo', 'bar'],
            ],
        ])
        ->and($openApiDocument['paths']['/test']['post']['parameters'] ?? [])
        ->toBeEmpty();
});
class RequestBodyExtensionTest__array_body
{
    public function __invoke(Illuminate\Http\Request $request)
    {
        $request->validate([
            '*.foo' => ['required', 'string'],
            '*.bar' => ['required', 'string'],
        ]);
    }
}

it('automatically infers multipart/form-data as request media type when some of body params is binary on a deeper layers', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__automaticall_infers_form_data_from_deeper::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content'])
        ->toHaveKey('multipart/form-data')
        ->toHaveLength(1);
});
class RequestBodyExtensionTest__automaticall_infers_form_data_from_deeper
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate([
            'foo.*' => 'file',
        ]);
    }
}

it('extracts parameters, their defaults, and descriptions from calling request parameters retrieving methods with scalar types', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_scalar_types::class, 'index']);
    });

    expect($schema = $openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toHaveLength(2)
        ->and($schema['properties'])
        ->toBe([
            'count' => [
                'type' => 'integer',
                'description' => 'How many things are there.',
                'default' => 10,
            ],
            'weight' => [
                'type' => 'number',
                'default' => 0.5,
            ],
            'is_foo' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'name' => [
                'type' => 'string',
                'default' => 'John Doe',
            ],
        ])
        ->and($schema['required'] ?? null)
        ->toBeNull();
});
class RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_scalar_types
{
    public function index(Illuminate\Http\Request $request)
    {
        // How many things are there.
        $param = $request->integer('count', 10);

        $request->float('weight', 0.5);

        $request->boolean('is_foo');

        $request->string('name', 'John Doe');
    }
}

it('extracts parameters, their defaults, and descriptions from calling request parameters retrieving methods with enum', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_enum::class, 'index']);
    });

    expect($properties = $openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'])
        ->toHaveLength(1)
        ->and($properties['status'])
        ->toBe([
            '$ref' => '#/components/schemas/RequestBodyExtensionTest__Status_Params_Extraction',
        ])
        ->and($openApiDocument['components']['schemas']['RequestBodyExtensionTest__Status_Params_Extraction'])
        ->toBe([
            'type' => 'string',
            'enum' => [
                'clubs',
                'diamonds',
                'hearts',
                'spades',
            ],
            'title' => 'RequestBodyExtensionTest__Status_Params_Extraction',
        ]);
});
class RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_enum
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->enum('status', RequestBodyExtensionTest__Status_Params_Extraction::class);
    }
}
enum RequestBodyExtensionTest__Status_Params_Extraction: string
{
    case Clubs = 'clubs';
    case Diamonds = 'diamonds';
    case Hearts = 'hearts';
    case Spades = 'spades';
}

it('extracts parameters, their defaults, and descriptions from calling request parameters retrieving methods with query', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_query::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['parameters'])
        ->toHaveLength(1)
        ->toBe([[
            'name' => 'in_query',
            'in' => 'query',
            'schema' => [
                'type' => 'string',
                'default' => 'foo',
            ],
        ]]);
});
class RequestBodyExtensionTest__extracts_parameters_from_retrieving_methods_with_query
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->query('in_query', 'foo');
    }
}

it('ignores parameter with @ignoreParam doc', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__ignores_parameter_with_ignore_param_doc::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? [])
        ->toHaveLength(0);
});
class RequestBodyExtensionTest__ignores_parameter_with_ignore_param_doc
{
    public function index(Illuminate\Http\Request $request)
    {
        /** @ignoreParam */
        $request->integer('foo', 10);
    }
}

it('uses and overrides default param value when it is provided manually in doc', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__uses_and_overrides_default_param_value_when_it_is_provided_manually_in_doc::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties']['foo']['default'])
        ->toBe(15);
});
class RequestBodyExtensionTest__uses_and_overrides_default_param_value_when_it_is_provided_manually_in_doc
{
    public function index(Illuminate\Http\Request $request)
    {
        /** @default 15 */
        $request->integer('foo', 10);
    }
}

it('allows explicitly specifying parameter placement in query manually in doc', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__allows_explicitly_specifying_parameter_placement_in_query_manually_in_doc::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? [])
        ->toBeEmpty()
        ->and($openApiDocument['paths']['/test']['post']['parameters'])
        ->toBe([[
            'name' => 'foo',
            'in' => 'query',
            'schema' => [
                'type' => 'integer',
                'default' => 10,
            ],
        ]]);
});
class RequestBodyExtensionTest__allows_explicitly_specifying_parameter_placement_in_query_manually_in_doc
{
    public function index(Illuminate\Http\Request $request)
    {
        /** @query */
        $request->integer('foo', 10);
    }
}

it('allows specifying query position and default for params inferred from validation rules using validate method', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::post('api/test', [RequestBodyExtensionTest__allows_specifying_query_position_and_default_for_params_inferred_from_validation_rules_using_validate_method::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'])
        ->toBe([
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
            ],
        ])
        ->and($openApiDocument['paths']['/test']['post']['parameters'])
        ->toBe([[
            'name' => 'all',
            'in' => 'query',
            'schema' => [
                'type' => 'boolean',
            ],
        ]]);
});
class RequestBodyExtensionTest__allows_specifying_query_position_and_default_for_params_inferred_from_validation_rules_using_validate_method
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate([
            /** @default 10 */
            'per_page' => 'integer',
            /** @query */
            'all' => 'boolean',
        ]);
    }
}

it('ignores param in rules with annotation', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{id}', [RequestBodyExtensionTest__ignores_rules_param_with_annotation::class, 'index']);
    });

    expect($params = $openApiDocument['paths']['/test/{id}']['get']['parameters'])
        ->toHaveCount(1)
        ->and($params[0]['in'])->toBe('path');
});
class RequestBodyExtensionTest__ignores_rules_param_with_annotation
{
    public function index(Illuminate\Http\Request $request, string $id)
    {
        $request->validate([
            /** @ignoreParam */
            'id' => 'integer',
        ]);
    }
}

it('makes reusable request body from marked validation rules', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', Validation_ReusableSchemaNamesTest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.FooObject')
        ->and($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/FooObject']);
});
class Validation_ReusableSchemaNamesTest_Controller
{
    public function __invoke(Request $request)
    {
        /**
         * @schemaName FooObject
         */
        $request->validate(['foo' => 'integer']);
    }
}

it('prefers nested parameters from validation in case defined in both places', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', Validation_PrefersParamsFromValidationTest_Controller::class);
    });

    expect($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'foo' => [
                    'type' => 'object',
                    'properties' => [
                        'fun' => [
                            'type' => 'integer',
                        ],
                        'bar' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'foo.thisOneShouldNotBeMerged' => [
                    'type' => 'string',
                ],
            ],
        ]);
});
class Validation_PrefersParamsFromValidationTest_Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['foo.fun' => 'integer']);

        // `foo.fun` should be removed as already defined
        $request->string('foo.fun');

        $request->string('foo.bar');

        $request->get('foo.thisOneShouldNotBeMerged');
    }
}

it('makes reusable request body from form request', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', FormRequest_ReusableSchemaNamesTest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.ReusableSchemaNamesTestFormRequest')
        ->and($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/ReusableSchemaNamesTestFormRequest']);
});
class FormRequest_ReusableSchemaNamesTest_Controller
{
    public function __invoke(ReusableSchemaNamesTestFormRequest $request) {}
}
class ReusableSchemaNamesTestFormRequest
{
    public function rules()
    {
        return ['foo' => 'integer'];
    }
}

it('allows to opt out from saving form request in schemas', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', FormRequest_OptOutSchemaNamesTest_Controller::class);
    });

    expect($document)->not->toHaveKey('components.schemas.OptOutSchemaNamesTestFormRequest')
        ->and($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'])
        ->toBe(['foo' => ['type' => 'integer']]);
});
class FormRequest_OptOutSchemaNamesTest_Controller
{
    public function __invoke(OptOutSchemaNamesTestFormRequest $request) {}
}
/** @ignoreSchema */
class OptOutSchemaNamesTestFormRequest extends FormRequest
{
    public function rules()
    {
        return ['foo' => 'integer'];
    }
}

it('allows to customize name and add description for form request in schemas', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', FormRequest_CustomSchemaNameFormRequest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.NiceSchemaNameRequest')
        ->and($document['components']['schemas']['NiceSchemaNameRequest']['description'])
        ->toBe('The request used to demonstrate that this feature is nice and works.');
});
class FormRequest_CustomSchemaNameFormRequest_Controller
{
    public function __invoke(CustomSchemaNameFormRequest $request) {}
}
/**
 * @schemaName NiceSchemaNameRequest
 *
 * The request used to demonstrate that this feature is nice and works.
 */
class CustomSchemaNameFormRequest extends FormRequest
{
    public function rules()
    {
        return ['foo' => 'integer'];
    }
}

it('allows to use validation on form request', function () {
    $routes = collect([
        RouteFacade::post('a', [AllowsBothFormRequestAndInlineValidationRules::class, 'a']),
        RouteFacade::post('b', [AllowsBothFormRequestAndInlineValidationRules::class, 'b']),
    ])->map->uri->toArray();

    Scramble::routes(fn (Route $r) => in_array($r->uri, $routes));

    $document = app()->make(\Dedoc\Scramble\Generator::class)();

    expect($document)->toMatchSnapshot();
});
class FormRequest_WithData extends FormRequest
{
    public function rules()
    {
        return ['foo' => 'string'];
    }
}
class AllowsBothFormRequestAndInlineValidationRules
{
    public function a(FormRequest_WithData $request)
    {
        $request->validate(['bar' => 'string']);
    }

    public function b(FormRequest_WithData $request)
    {
        $request->validate(['baz' => 'numeric']);
    }
}

it('allows to add description for validation calls in schemas', function () {
    $document = generateForRoute(function () {
        return RouteFacade::post('test', Validation_DescriptionSchemaNamesTest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.FooObject')
        ->and($document['components']['schemas']['FooObject']['description'])
        ->toBe('Wow.');
});
class Validation_DescriptionSchemaNamesTest_Controller
{
    public function __invoke(Request $request)
    {
        /**
         * @schemaName FooObject
         *
         * Wow.
         */
        $request->validate(['foo' => 'integer']);
    }
}

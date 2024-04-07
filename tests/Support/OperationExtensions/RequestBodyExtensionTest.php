<?php

use Illuminate\Support\Facades\Route as RouteFacade;

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
        $request->integer('count', 10);

        $request->float('weight', 0.5);

        $request->boolean('is_foo');

        $request->string('name', 'John Doe');

//        $request->enum('status', RequestBodyExtensionTest__Status_Params_Extraction::class);
//
//        $request->query('in_query');
    }
}
enum RequestBodyExtensionTest__Status_Params_Extraction: string {
    case Clubs = 'clubs';
    case Diamonds = 'diamonds';
    case Hearts = 'hearts';
    case Spades = 'spades';
}

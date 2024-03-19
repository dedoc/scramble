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

it('does generate for delete by default', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::delete('api/test', [RequestBodyExtensionTest__does_generate_by_default_delete::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['delete'])
        ->toHaveKey('requestBody')
        ->and($openApiDocument['paths']['/test']['delete'])
        ->not()->toHaveKey('parameters')
        ->and($openApiDocument['paths']['/test']['delete']['requestBody']['content'])
        ->toHaveKey('application/json')
        ->toHaveLength(1)
        ->and(
            data_get(
                $openApiDocument,
                'paths./test.delete.requestBody.content.application/json.schema.properties'
            )
        )
        ->toHaveKey('foo')
        ->toHaveKey('foo.type', 'string');

});
class RequestBodyExtensionTest__does_generate_by_default_delete
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('does generate for head by default', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::addRoute('head', 'api/test', [RequestBodyExtensionTest__does_generate_by_default_head::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['head'])
        ->toHaveKey('requestBody')
        ->and($openApiDocument['paths']['/test']['head'])
        ->not()->toHaveKey('parameters')
        ->and($openApiDocument['paths']['/test']['head']['requestBody']['content'])
        ->toHaveKey('application/json')
        ->toHaveLength(1)
        ->and(
            data_get(
                $openApiDocument,
                'paths./test.head.requestBody.content.application/json.schema.properties'
            )
        )
        ->toHaveKey('foo')
        ->toHaveKey('foo.type', 'string');

});
class RequestBodyExtensionTest__does_generate_by_default_head
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('does not generate for get by default', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [RequestBodyExtensionTest__does_not_generate_by_default_get::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->not()->toHaveKey('requestBody')
        ->and($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('parameters')
        ->and($openApiDocument['paths']['/test']['get']['parameters'])
        ->toHaveLength(1)
        ->and($openApiDocument['paths']['/test']['get']['parameters']['0'])
        ->toHaveKey('name', 'foo')
        ->toHaveKey('in', 'query');

});
class RequestBodyExtensionTest__does_not_generate_by_default_get
{
    public function index(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'string']);
    }
}

it('does not generate for delete when configured', function () {
    config()->set('scramble.disallow_request_body', ['get', 'delete']);

    $openApiDocument = generateForRoute(function () {
        return RouteFacade::delete('api/test', [RequestBodyExtensionTest__does_not_generate_when_configured_delete::class, 'index']);
    });

    expect($openApiDocument['paths']['/test']['delete'])
        ->not()->toHaveKey('requestBody')
        ->and($openApiDocument['paths']['/test']['delete'])
        ->toHaveKey('parameters')
        ->and($openApiDocument['paths']['/test']['delete']['parameters'])
        ->toHaveLength(1)
        ->and($openApiDocument['paths']['/test']['delete']['parameters']['0'])
        ->toHaveKey('name', 'foo')
        ->toHaveKey('in', 'query');

});
class RequestBodyExtensionTest__does_not_generate_when_configured_delete
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

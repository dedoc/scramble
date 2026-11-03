<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\Rule;

if (trait_exists(HasUuids::class)) {
    it('documents model keys uuid parameters as uuids', function () {
        $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test/{model}', [
            DocumentsModelKeysUuidParametersAsUuids_Test::class, 'index',
        ]));

        expect($params = $openApiDocument['paths']['/test/{model}']['get']['parameters'])
            ->toHaveCount(1)
            ->and($params[0])
            ->toMatchArray([
                'name' => 'model',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
            ]);
    });

    class DocumentsModelKeysUuidParametersAsUuids_Test
    {
        public function index(DocumentsModelKeysUuidParametersAsUuids_Model $model)
        {
            return response()->json();
        }
    }

    class DocumentsModelKeysUuidParametersAsUuids_Model extends \Illuminate\Database\Eloquent\Model
    {
        use HasUuids;
    }
}

if (trait_exists(HasVersion4Uuids::class)) {
    it('documents model keys uuid v4 parameters as uuids', function () {
        $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test/{model}', [
            DocumentsModelKeysUuidV4ParametersAsUuids_Test::class, 'index',
        ]));

        expect($params = $openApiDocument['paths']['/test/{model}']['get']['parameters'])
            ->toHaveCount(1)
            ->and($params[0])
            ->toMatchArray([
                'name' => 'model',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
            ]);
    });

    class DocumentsModelKeysUuidV4ParametersAsUuids_Test
    {
        public function index(DocumentsModelKeysUuidV4ParametersAsUuids_Model $model)
        {
            return response()->json();
        }
    }

    class DocumentsModelKeysUuidV4ParametersAsUuids_Model extends \Illuminate\Database\Eloquent\Model
    {
        use HasVersion4Uuids;
    }
}

it('supports @format annotation for validation rules', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', SupportFormatAnnotation_ParametersDocumentationTestController::class));

    expect($openApiDocument['paths']['/test']['get']['parameters'])
        ->toHaveCount(1)
        ->and($openApiDocument['paths']['/test']['get']['parameters'][0])
        ->toBe([
            'name' => 'foo',
            'in' => 'query',
            'required' => true,
            'schema' => [
                'type' => 'string',
                'format' => 'uuid',
            ],
        ]);
});

class SupportFormatAnnotation_ParametersDocumentationTestController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            /** @format uuid */
            'foo' => ['required'],
        ]);
    }
}

it('supports optional parameters', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test/{payment_preference?}', SupportOptionalParam_ParametersDocumentationTestController::class));

    expect($openApiDocument['paths']['/test/{paymentPreference}']['get']['parameters'])
        ->toHaveCount(1)
        ->and($openApiDocument['paths']['/test/{paymentPreference}']['get']['parameters'][0])
        ->toBe([
            'name' => 'paymentPreference',
            'in' => 'path',
            'required' => true,
            'description' => '**Optional**. The name of the payment preference to use',
            'schema' => [
                'type' => ['string', 'null'],
                'default' => 'paypal',
            ],
            'x-optional' => true,
        ]);
});

class SupportOptionalParam_ParametersDocumentationTestController
{
    /**
     * @param  string|null  $paymentPreference  The name of the payment preference to use
     */
    public function __invoke(?string $paymentPreference = 'paypal') {}
}

it('documents array query parameters with enum items without moving the enum to the array', function () {
    $document = generateForRoute(fn () => RouteFacade::get('test', ArrayEnumQueryParameters_ParametersDocumentationTestController::class));

    expect($parameters = $document['paths']['/test']['get']['parameters'])
        ->toHaveCount(1)
        ->and($parameters[0])
        ->toBe([
            'name' => 'include[]',
            'in' => 'query',
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['metadata'],
                ],
            ],
        ]);
});

class ArrayEnumQueryParameters_ParametersDocumentationTestController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'include' => ['sometimes', 'array'],
            'include.*' => ['string', Rule::in(['metadata'])],
        ]);
    }
}

it('documents nullable array query parameters with nullable items', function () {
    $document = generateForRoute(fn () => RouteFacade::get('test', NullableArrayQueryParameters_ParametersDocumentationTestController::class));

    expect($parameters = $document['paths']['/test']['get']['parameters'])
        ->toHaveCount(1)
        ->and($parameters[0])
        ->toBe([
            'name' => 'tags[]',
            'in' => 'query',
            'schema' => [
                'type' => ['array', 'null'],
                'items' => [
                    'type' => ['integer', 'null'],
                ],
            ],
        ]);
});

class NullableArrayQueryParameters_ParametersDocumentationTestController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|integer',
        ]);
    }
}

it('documents array and item nullability independently', function () {
    $document = generateForRoute(fn () => RouteFacade::get('test', ArrayAndItemNullability_ParametersDocumentationTestController::class));

    expect($document['paths']['/test']['get']['parameters'])
        ->toBe([
            [
                'name' => 'filter[tags][]',
                'in' => 'query',
                'schema' => [
                    'type' => ['array', 'null'],
                    'items' => [
                        'type' => 'integer',
                    ],
                    'minItems' => 1.0,
                    'maxItems' => 3.0,
                ],
            ],
            [
                'name' => 'tags[]',
                'in' => 'query',
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => ['integer', 'null'],
                    ],
                ],
            ],
        ]);
});

class ArrayAndItemNullability_ParametersDocumentationTestController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'filter.tags' => 'nullable|array|min:1|max:3',
            'filter.tags.*' => 'integer',
            'tags.*' => 'nullable|integer',
        ]);
    }
}

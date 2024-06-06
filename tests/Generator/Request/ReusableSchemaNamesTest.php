<?php

// @todo: move the tests to Support/... file (corresponding to the file being tested)

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('makes reusable request body from marked validation rules', function () {
    $document = generateForRoute(function () {
        return Route::post('test', Validation_ReusableSchemaNamesTest_Controller::class);
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

it('makes reusable request body from form request', function () {
    $document = generateForRoute(function () {
        return Route::post('test', FormRequest_ReusableSchemaNamesTest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.ReusableSchemaNamesTestFormRequest')
        ->and($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/ReusableSchemaNamesTestFormRequest']);
});
class FormRequest_ReusableSchemaNamesTest_Controller
{
    public function __invoke(ReusableSchemaNamesTestFormRequest $request)
    {
    }
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
        return Route::post('test', FormRequest_OptOutSchemaNamesTest_Controller::class);
    });

    expect($document)->not->toHaveKey('components.schemas.OptOutSchemaNamesTestFormRequest')
        ->and($document['paths']['/test']['post']['requestBody']['content']['application/json']['schema']['properties'])
        ->toBe(['foo' => ['type' => 'integer']]);
});
class FormRequest_OptOutSchemaNamesTest_Controller
{
    public function __invoke(OptOutSchemaNamesTestFormRequest $request)
    {
    }
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
        return Route::post('test', FormRequest_CustomSchemaNameFormRequest_Controller::class);
    });

    expect($document)->toHaveKey('components.schemas.NiceSchemaNameRequest')
        ->and($document['components']['schemas']['NiceSchemaNameRequest']['description'])
        ->toBe('The request used to demonstrate that this feature is nice and works.');
});
class FormRequest_CustomSchemaNameFormRequest_Controller
{
    public function __invoke(CustomSchemaNameFormRequest $request)
    {
    }
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

it('allows to add description for validation calls in schemas', function () {
    $document = generateForRoute(function () {
        return Route::post('test', Validation_DescriptionSchemaNamesTest_Controller::class);
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

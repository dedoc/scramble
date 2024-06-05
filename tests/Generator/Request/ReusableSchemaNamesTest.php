<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('makes reusable request body from marked validation rules', function () {
    $document = generateForRoute(function () {
        return Route::get('test', Validation_ReusableSchemaNamesTest_Controller::class);
    });

    // assert document has request body and a reference to it
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
        return Route::get('test', FormRequest_ReusableSchemaNamesTest_Controller::class);
    });

    // assert document has request body and a reference to it
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

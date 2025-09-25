<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

it('extracts form request parameters from class', function () {
    $openApi = $this->generateForRoute(function () {
        return Route::post('/test', ControllerA_FormRequestRulesExtractorPestTest::class);
    });

    expect($openApi['components']['schemas']['FormRequestA_FormRequestRulesExtractorPestTest']['properties'])
        ->toHaveKey('foo');
});
class ControllerA_FormRequestRulesExtractorPestTest
{
    public function __invoke(FormRequestA_FormRequestRulesExtractorPestTest $request) {}
}
class FormRequestA_FormRequestRulesExtractorPestTest
{
    public function rules()
    {
        return [
            'foo' => 'required',
        ];
    }
}

it('extracts form request parameters for closure route class', function () {
    $openApi = generateForRoute(Route::post(
        '/test',
        fn (FormRequestA_FormRequestRulesExtractorPestTest $request) => null,
    ));

    expect($openApi['components']['schemas']['FormRequestA_FormRequestRulesExtractorPestTest']['properties'])
        ->toHaveKey('foo');
});

it('extracts parameters with route parameter bindings', function () {
    $openApi = $this->generateForRoute(function () {
        return Route::post('/test/{user}', ControllerB_FormRequestRulesExtractorPestTest::class);
    });

    expect($openApi['components']['schemas']['FormRequestB_FormRequestRulesExtractorPestTest']['properties'])
        ->toHaveKey('foo');
});
class ControllerB_FormRequestRulesExtractorPestTest
{
    public function __invoke(FormRequestB_FormRequestRulesExtractorPestTest $request, User_FormRequestRulesExtractorPestTest $user) {}
}
class FormRequestB_FormRequestRulesExtractorPestTest
{
    public function rules()
    {
        return [
            'foo' => ['required', 'integer', Rule::in([$this->user->getNumbers()])],
        ];
    }
}

it('extracts parameters with route parameter bindings in concat', function () {
    $openApi = $this->generateForRoute(function () {
        return Route::post('/test/{user}', ControllerC_FormRequestRulesExtractorPestTest::class);
    });

    expect($openApi['components']['schemas']['FormRequestC_FormRequestRulesExtractorPestTest']['properties'])
        ->toHaveKey('foo');
});
class ControllerC_FormRequestRulesExtractorPestTest
{
    public function __invoke(FormRequestC_FormRequestRulesExtractorPestTest $request, User_FormRequestRulesExtractorPestTest $user) {}
}
class FormRequestC_FormRequestRulesExtractorPestTest
{
    public function rules()
    {
        return [
            'foo' => 'required|integer|'.Rule::in([$this->user->getNumbers()]),
        ];
    }
}

class User_FormRequestRulesExtractorPestTest extends Model
{
    protected $table = 'users';

    public function getNumbers()
    {
        return range(1, $this->id);
    }
}

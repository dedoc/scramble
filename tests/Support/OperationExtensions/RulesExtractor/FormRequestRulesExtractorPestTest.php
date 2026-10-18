<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
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

it('extracts form request parameters spread from a trait method', function () {
    app()->instance(ValidationRule::class, new class implements ValidationRule
    {
        public function validate(string $attribute, mixed $value, \Closure $fail): void {}
    });

    $openApi = $this->generateForRoute(function () {
        return Route::post('/test', ControllerWithSpreadRules_FormRequestRulesExtractorPestTest::class);
    });

    $schema = $openApi['components']['schemas']['FormRequestWithSpreadRules_FormRequestRulesExtractorPestTest'];

    expect($schema['properties'])->toHaveKeys(['name', 'extra']);
});

it('preserves spread trait rules when a rule dependency cannot be instantiated', function () {
    $result = generateForRoute(
        Route::post('/test', ControllerWithSpreadRules_FormRequestRulesExtractorPestTest::class),
        result: true,
    );

    $openApi = $result->spec();

    expect($openApi['components']['schemas']['FormRequestWithSpreadRules_FormRequestRulesExtractorPestTest'])->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'extra' => ['type' => 'string'],
        ],
        'title' => 'FormRequestWithSpreadRules_FormRequestRulesExtractorPestTest',
    ])->and($result->diagnostics()->all())->toBeEmpty();
});

class ControllerWithSpreadRules_FormRequestRulesExtractorPestTest
{
    public function __invoke(FormRequestWithSpreadRules_FormRequestRulesExtractorPestTest $request) {}
}

class FormRequestWithSpreadRules_FormRequestRulesExtractorPestTest extends FormRequest
{
    use HasExtraRules_FormRequestRulesExtractorPestTest;

    public function rules(ValidationRule $rule): array
    {
        return [
            'name' => ['string'],
            ...$this->extraRules(),
        ];
    }
}

trait HasExtraRules_FormRequestRulesExtractorPestTest
{
    public function extraRules(): array
    {
        return [
            'extra' => ['string', app(ValidationRule::class)],
        ];
    }
}

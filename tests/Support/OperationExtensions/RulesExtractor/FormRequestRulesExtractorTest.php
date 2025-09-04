<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class FormRequestRulesExtractorTest extends TestCase
{
    /** @test */
    #[DefineEnvironment('registerInterfaceBasedRequest')]
    public function resolves_form_request_using_interface()
    {
        $openApi = $this->generateForRoute(function () {
            return Route::post('/test', FormRequestRulesExtractorTestController::class);
        });

        $requestSchema = $openApi['components']['schemas']['ConcreteDataRequest'];
        $properties = $requestSchema['properties'];
        expect($properties)->toHaveKey('foo');
        expect($properties)->toHaveKey('bar');
        expect($properties)->toHaveKey('baz');
        expect($requestSchema)->toHaveKey('required');

        $requiredProperties = $requestSchema['required'];
        expect($requiredProperties)->toHaveCount(1);
        expect($requiredProperties[0])->toEqual('foo');
    }

    protected function registerInterfaceBasedRequest(Application $app)
    {
        $app->bind(DataRequestContract::class, ConcreteDataRequest::class);
    }
}

interface DataRequestContract
{
    public function rules();
}

class ConcreteDataRequest extends FormRequest implements DataRequestContract
{
    public function rules()
    {
        return [
            'foo' => 'required',
            'bar' => Rule::requiredIf(fn () => true),
            'baz' => 'required_if:foo,1',
        ];
    }
}

class FormRequestRulesExtractorTestController
{
    public function __invoke(DataRequestContract $request) {}
}

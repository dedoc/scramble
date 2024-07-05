<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
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

        expect($openApi['components']['schemas']['ConcreteDataRequest']['properties'])
            ->toHaveKey('foo');
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
        return ['foo' => 'required'];
    }
}

class FormRequestRulesExtractorTestController
{
    public function __invoke(DataRequestContract $request) {}
}

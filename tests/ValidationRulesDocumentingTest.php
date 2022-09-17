<?php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameter;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\Rule;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('extract rules from array like rules', function () {
    $rules = [
        'id' => 'int',
        'some' => 'required|array',
        'some.*.id' => 'required|int',
        'some.*.name' => 'string',
    ];

    $params = (new RulesToParameters($rules))->handle();

    assertMatchesSnapshot(collect($params)->map->toArray()->all());
});

it('extracts rules from request->validate call', function () {
    RouteFacade::get('test', [ValidationRulesDocumenting_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('supports validation rules and form request at the same time', function () {
    RouteFacade::get('test', [ValidationRulesAndFormRequestAtTheSameTime_Test::class, 'index']);

    Scramble::routes(fn (Route $r) => $r->uri === 'test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

class ValidationRulesDocumenting_Test
{
    /**
     * @return ValidationRulesDocumenting_TestResource
     */
    public function index(Request $request)
    {
        $request->validate([
            'content' => ['required', Rule::in('wow')],
        ]);

        return new ValidationRulesDocumenting_TestResource(12);
    }
}

class ValidationRulesDocumenting_TestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => 1,
        ];
    }
}

class ValidationRulesAndFormRequestAtTheSameTime_Test
{
    public function index(ValidationRulesAndFormRequestAtTheSameTime_TestFormRequest $request)
    {
        $request->validate([
            'from_validate_call' => ['required', 'string'],
        ]);
    }
}

class ValidationRulesAndFormRequestAtTheSameTime_TestFormRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from_form_request' => 'int',
        ];
    }
}

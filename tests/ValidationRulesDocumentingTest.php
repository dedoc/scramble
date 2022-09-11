<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\Rule;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('extracts rules from request->validate call', function () {
    RouteFacade::get('test', [ValidationRulesDocumenting_Test::class, 'index']);

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

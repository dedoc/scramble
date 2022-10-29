<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('adds validation error response', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_validation_error_response']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    dd($openApiDocument);

    assertMatchesSnapshot($openApiDocument);
})->skip();
class ErrorsResponsesTest_Controller
{
    public function adds_validation_error_response(\Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'required']);
    }
}

<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('adds validation error response', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_validation_error_response']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('adds validation error response with facade made validators', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_validation_error_response_with_facade_made_validators']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('adds auth error response', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_auth_error_response']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('adds not found error response', function () {
    RouteFacade::get('api/test/{user}', [ErrorsResponsesTest_Controller::class, 'adds_not_found_error_response'])
        ->middleware('can:update,post');

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test/{user}');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class ErrorsResponsesTest_Controller extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function adds_validation_error_response(Illuminate\Http\Request $request)
    {
        $request->validate(['foo' => 'required']);
    }

    public function adds_validation_error_response_with_facade_made_validators(Illuminate\Http\Request $request)
    {
        \Illuminate\Support\Facades\Validator::make($request->all(), ['foo' => 'required'])
            ->validate();
    }

    public function adds_auth_error_response(Illuminate\Http\Request $request)
    {
        $this->authorize('read');
    }

    public function adds_not_found_error_response(Illuminate\Http\Request $request, UserModel_ErrorsResponsesTest $user)
    {
    }
}

class UserModel_ErrorsResponsesTest
{
}

<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

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

it('adds errors responses with custom requests', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_errors_with_custom_request']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('doesnt add errors with custom request when errors producing methods are not defined', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'doesnt_add_errors_with_custom_request_when_errors_producing_methods_not_defined']);
    });

    expect($openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKeys([200])
        ->toHaveCount(1);
});

it('adds authorization error response', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_authorization_error_response']);
    });

    assertMatchesSnapshot($openApiDocument);
});

it('adds authorization error response for gate authorize', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_authorization_error_response_gate']);
    });

    assertMatchesSnapshot($openApiDocument);
});

it('adds authentication error response', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'adds_authorization_error_response'])
            ->middleware('auth');
    });

    expect($openApiDocument)
        ->toHaveKey('components.responses.AuthenticationException')
        ->and($openApiDocument['paths']['/test']['get']['responses'][401])
        ->toBe([
            '$ref' => '#/components/responses/AuthenticationException',
        ]);
});

it('adds not found error response with can directive', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{user}', [ErrorsResponsesTest_Controller::class, 'adds_not_found_error_response'])
            ->middleware('can:update,post');
    });

    assertMatchesSnapshot($openApiDocument);
});

it('adds not found error response with Authorize::using', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test/{user}', [ErrorsResponsesTest_Controller::class, 'adds_not_found_error_response'])
            ->middleware(Authorize::using('update', 'post'));
    });

    assertMatchesSnapshot($openApiDocument);
});

it('adds validation error response when documented in phpdoc', function () {
    RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'phpdoc_exception_response']);

    Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});

it('adds http error response exception extending HTTP exception is thrown', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'custom_exception_response']));

    expect($openApiDocument['paths']['/test']['get']['responses'][409])->toHaveKey('content.application/json.schema.type', 'object');
});

it('adds http error response exception extending sympony HTTP exception is thrown', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', [ErrorsResponsesTest_Controller::class, 'symfony_http_exception_response']));

    // AccessDeniedHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][403])->toHaveKey('content.application/json.schema.type', 'object');
    // BadRequestHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][400])->toHaveKey('content.application/json.schema.type', 'object');
    // ConflictHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][409])->toHaveKey('content.application/json.schema.type', 'object');
    // GoneHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][410])->toHaveKey('content.application/json.schema.type', 'object');
    // LengthRequiredHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][411])->toHaveKey('content.application/json.schema.type', 'object');
    // LockedHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][423])->toHaveKey('content.application/json.schema.type', 'object');
    // MethodNotAllowedHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][405])->toHaveKey('content.application/json.schema.type', 'object');
    // NotAcceptableHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][406])->toHaveKey('content.application/json.schema.type', 'object');
    // PreconditionFailedHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][412])->toHaveKey('content.application/json.schema.type', 'object');
    // PreconditionRequiredHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][428])->toHaveKey('content.application/json.schema.type', 'object');
    // ServiceUnavailableHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][503])->toHaveKey('content.application/json.schema.type', 'object');
    // TooManyRequestsHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][429])->toHaveKey('content.application/json.schema.type', 'object');
    // UnauthorizedHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][401])->toHaveKey('content.application/json.schema.type', 'object');
    // UnprocessableEntityHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][422])->toHaveKey('content.application/json.schema.type', 'object');
    // UnsupportedMediaTypeHttpException
    expect($openApiDocument['paths']['/test']['get']['responses'][415])->toHaveKey('content.application/json.schema.type', 'object');
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

    public function adds_errors_with_custom_request(ErrorsResponsesTest_Controller_CustomRequest $request) {}

    public function doesnt_add_errors_with_custom_request_when_errors_producing_methods_not_defined(ErrorsResponsesTest_Controller_CustomRequestWithoutErrorCreatingMethods $request) {}

    public function adds_authorization_error_response(Illuminate\Http\Request $request)
    {
        $this->authorize('read');
    }

    public function adds_authorization_error_response_gate()
    {
        Gate::authorize('read');
    }

    public function adds_authentication_error_response(Illuminate\Http\Request $request) {}

    public function adds_not_found_error_response(Illuminate\Http\Request $request, UserModel_ErrorsResponsesTest $user) {}

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function phpdoc_exception_response(Illuminate\Http\Request $request) {}

    public function custom_exception_response(Illuminate\Http\Request $request)
    {
        throw new BusinessException('The business error');
    }

    /**
     * @throws AccessDeniedHttpException|BadRequestHttpException|ConflictHttpException|GoneHttpException|LengthRequiredHttpException|LockedHttpException|MethodNotAllowedHttpException|NotAcceptableHttpException|PreconditionFailedHttpException|PreconditionRequiredHttpException|ServiceUnavailableHttpException|TooManyRequestsHttpException|UnauthorizedHttpException|UnprocessableEntityHttpException|UnsupportedMediaTypeHttpException
     */
    public function symfony_http_exception_response(Illuminate\Http\Request $request) {}
}

class BusinessException extends \Symfony\Component\HttpKernel\Exception\HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct(409, $message, $previous, $headers, $code);
    }
}

class UserModel_ErrorsResponsesTest extends \Illuminate\Database\Eloquent\Model {}

class ErrorsResponsesTest_Controller_CustomRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize()
    {
        return something();
    }

    public function rules()
    {
        return ['foo' => 'required'];
    }
}

class ErrorsResponsesTest_Controller_CustomRequestWithoutErrorCreatingMethods extends \Illuminate\Foundation\Http\FormRequest {}

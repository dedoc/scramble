<?php

use Dedoc\Scramble\Attributes\Api;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

function ApiAttributeFilteringTest_paths(string $api): array
{
    return array_keys(app(Generator::class)(Scramble::getGeneratorConfig($api))['paths'] ?? []);
}

beforeEach(function () {
    Scramble::ignoreDefaultRoutes();

    Scramble::registerApi('public', [
        'api_path' => '',
    ])->routes(fn (Route $r) => str_starts_with($r->uri, 'api-attr/'));

    Scramble::registerApi('internal', [
        'api_path' => '',
    ])->routes(fn (Route $r) => str_starts_with($r->uri, 'api-attr/'));
});

it('includes routes without Api attribute in every matching API', function () {
    RouteFacade::get('api-attr/open', [ApiAttributeFilteringTest_Controller::class, 'open']);

    expect(ApiAttributeFilteringTest_paths('public'))->toBe(['/api-attr/open'])
        ->and(ApiAttributeFilteringTest_paths('internal'))->toBe(['/api-attr/open']);
});

it('includes Api-annotated routes only in the named APIs', function () {
    RouteFacade::get('api-attr/internal-only', [ApiAttributeFilteringTest_Controller::class, 'internalOnly']);
    RouteFacade::get('api-attr/both', [ApiAttributeFilteringTest_Controller::class, 'both']);

    expect(ApiAttributeFilteringTest_paths('public'))->toBe(['/api-attr/both'])
        ->and(ApiAttributeFilteringTest_paths('internal'))->toBe([
            '/api-attr/internal-only',
            '/api-attr/both',
        ]);
});

it('applies class-level Api attribute to all controller methods', function () {
    RouteFacade::get('api-attr/class-a', [ApiAttributeFilteringTest_InternalController::class, 'a']);
    RouteFacade::get('api-attr/class-b', [ApiAttributeFilteringTest_InternalController::class, 'b']);

    expect(ApiAttributeFilteringTest_paths('public'))->toBe([])
        ->and(ApiAttributeFilteringTest_paths('internal'))->toBe([
            '/api-attr/class-a',
            '/api-attr/class-b',
        ]);
});

it('lets method-level Api attribute override class-level attribute', function () {
    RouteFacade::get('api-attr/override-default', [ApiAttributeFilteringTest_OverrideController::class, 'defaulted']);
    RouteFacade::get('api-attr/override-public', [ApiAttributeFilteringTest_OverrideController::class, 'publicOnly']);

    expect(ApiAttributeFilteringTest_paths('public'))->toBe(['/api-attr/override-public'])
        ->and(ApiAttributeFilteringTest_paths('internal'))->toBe(['/api-attr/override-default']);
});

it('does not re-include routes excluded by ExcludeRouteFromDocs', function () {
    RouteFacade::get('api-attr/excluded', [ApiAttributeFilteringTest_Controller::class, 'excluded']);

    expect(ApiAttributeFilteringTest_paths('internal'))->toBe([]);
});

it('does not include Api-annotated routes rejected by the route resolver', function () {
    RouteFacade::get('other/internal-only', [ApiAttributeFilteringTest_Controller::class, 'internalOnly']);

    expect(ApiAttributeFilteringTest_paths('internal'))->toBe([]);
});

class ApiAttributeFilteringTest_Controller
{
    public function open() {}

    #[Api('internal')]
    public function internalOnly() {}

    #[Api('public', 'internal')]
    public function both() {}

    #[Api('internal')]
    #[ExcludeRouteFromDocs]
    public function excluded() {}
}

#[Api('internal')]
class ApiAttributeFilteringTest_InternalController
{
    public function a() {}

    public function b() {}
}

#[Api('internal')]
class ApiAttributeFilteringTest_OverrideController
{
    public function defaulted() {}

    #[Api('public')]
    public function publicOnly() {}
}

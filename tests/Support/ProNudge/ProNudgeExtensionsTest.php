<?php

use Dedoc\Scramble\Console\Commands\AnalyzeDocumentation;
use Dedoc\Scramble\Console\Commands\ExportDocumentation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ProNudge\ProNudgeReporter;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

use function Pest\Laravel\artisan;

require_once __DIR__.'/stubs/LaravelData.php';
require_once __DIR__.'/stubs/QueryBuilder.php';

it('collects laravel data return type signals', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));

    RouteFacade::get('api/pro-nudge/data-return', [ProNudge_DataReturn_Controller::class, 'index']);
    RouteFacade::get('api/pro-nudge/plain', [ProNudge_Plain_Controller::class, 'index']);

    $result = app(Generator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    expect($result->proNudge()->message()['title'])->toBe('1 endpoint returns Laravel Data objects');
});

it('collects laravel data request body signals', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));

    RouteFacade::post('api/pro-nudge/data-request', [ProNudge_DataRequest_Controller::class, 'store']);
    RouteFacade::post('api/pro-nudge/plain-request', [ProNudge_Plain_Controller::class, 'store']);

    $result = app(Generator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    expect($result->proNudge()->message()['title'])->toBe('1 endpoint accepts Laravel Data objects');
});

it('collects query builder usage signals', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));

    RouteFacade::get('api/pro-nudge/query-builder', [ProNudge_QueryBuilder_Controller::class, 'index']);
    RouteFacade::get('api/pro-nudge/plain', [ProNudge_Plain_Controller::class, 'index']);

    $result = app(Generator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    expect($result->proNudge()->message()['title'])->toBe('1 endpoint uses Spatie Query Builder');
});

it('resets pro nudge collector between generator runs', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));

    RouteFacade::get('api/pro-nudge/data-return', [ProNudge_DataReturn_Controller::class, 'index']);

    $generator = app(Generator::class);
    $result = $generator->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    expect($result->proNudge()->message()['title'])->toBe('1 endpoint returns Laravel Data objects');

    Scramble::routes(fn (Route $r) => false);
    $result = $generator->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    expect($result->proNudge()->message())->toBeNull();
});

it('prints pro nudge after export when signals are present', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));

    RouteFacade::get('api/pro-nudge/data-return', [ProNudge_DataReturn_Controller::class, 'index']);
    RouteFacade::post('api/pro-nudge/data-request', [ProNudge_DataRequest_Controller::class, 'store']);
    RouteFacade::get('api/pro-nudge/query-builder', [ProNudge_QueryBuilder_Controller::class, 'index']);

    File::shouldReceive('put')->once();

    artisan(ExportDocumentation::class)
        ->expectsOutputToContain('OpenAPI document exported to api.json.')
        ->expectsOutputToContain('1 endpoint uses Spatie Query Builder')
        ->expectsOutputToContain('Scramble PRO will document these endpoints accurately.')
        ->expectsOutputToContain('Learn more: '.ProNudgeReporter::PRO_URL)
        ->assertOk();
});

it('prints diagnostics and then pro nudge when analyzing documentation', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));
    Scramble::configure()->withDocumentTransformers(function (OpenApi $_, OpenApiContext $context) {
        $context->diagnostics->report(new GenericDiagnostic(DiagnosticSeverity::Warning, 'Test diagnostic warning'));
    });

    RouteFacade::get('api/pro-nudge/data-return', [ProNudge_DataReturn_Controller::class, 'index']);

    artisan(AnalyzeDocumentation::class)
        ->expectsOutputToContain('Test diagnostic warning')
        ->expectsOutputToContain('1 endpoint returns Laravel Data objects')
        ->expectsOutputToContain('Scramble PRO will document these endpoints accurately.')
        ->assertOk();
});

it('exports documentation and then prints pro nudge', function () {
    Scramble::routes(fn (Route $r) => str_starts_with($r->uri, 'api/pro-nudge'));
    Scramble::configure()->withDocumentTransformers(function (OpenApi $_, OpenApiContext $context) {
        $context->diagnostics->report(new GenericDiagnostic(DiagnosticSeverity::Error, 'Test diagnostic error'));
    });

    RouteFacade::get('api/pro-nudge/data-return', [ProNudge_DataReturn_Controller::class, 'index']);

    File::shouldReceive('put')->once();

    artisan(ExportDocumentation::class)
        ->expectsOutputToContain('OpenAPI document exported to')
        ->expectsOutputToContain('1 endpoint returns Laravel Data objects')
        ->expectsOutputToContain('Scramble PRO will document these endpoints accurately.')
        ->assertOk();
});

class ProNudge_DataReturn_Controller
{
    public function index(): ProNudge_SampleData
    {
        return new ProNudge_SampleData;
    }
}

class ProNudge_DataRequest_Controller
{
    public function store(ProNudge_SampleData $data): array
    {
        return [];
    }
}

class ProNudge_QueryBuilder_Controller
{
    public function index()
    {
        return \Spatie\QueryBuilder\QueryBuilder::for(ProNudge_SampleModel::class)
            ->allowedFilters('name')
            ->get();
    }
}

class ProNudge_Plain_Controller
{
    public function index(): array
    {
        return [];
    }

    public function store(): array
    {
        return [];
    }
}

class ProNudge_SampleData extends \Spatie\LaravelData\Data {}

class ProNudge_SampleModel extends \Illuminate\Database\Eloquent\Model {}

<?php

use Dedoc\Scramble\Console\Commands\AnalyzeDocumentation;
use Dedoc\Scramble\Console\Commands\ExportDocumentation;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function Pest\Laravel\artisan;

it('should export the documentation', function () {
    $generator = app(Generator::class);
    $path = 'api.json';

    File::shouldReceive('put')
        ->once()
        ->with(
            $path,
            json_encode($generator(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class)->assertOk();
});

it('should export the documentation to the path specified by the --path option', function () {
    $generator = app(Generator::class);
    $path = 'api-test.json';

    File::shouldReceive('put')
        ->once()
        ->with(
            $path,
            json_encode($generator(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--path' => $path,
    ])->assertOk();
});

it('filters exported documentation by comma-separated route names', function () {
    RouteFacade::get('api/route-filter-a', [RouteFilterController::class, 'a'])->name('route-filter.a');
    RouteFacade::get('api/route-filter-b', [RouteFilterController::class, 'b'])->name('route-filter.b');
    RouteFacade::get('api/route-filter-c', [RouteFilterController::class, 'c'])->name('route-filter.c');

    File::shouldReceive('put')
        ->once()
        ->with('api.json', \Mockery::on(function (string $specification) {
            $paths = array_keys(json_decode($specification, true)['paths']);

            return $paths === ['/route-filter-a', '/route-filter-c'];
        }));

    artisan(ExportDocumentation::class, [
        '--routes' => 'route-filter.a, route-filter.c',
    ])->assertOk();
});

it('exports only JSON with a trailing newline to stdout', function () {
    RouteFacade::get('api/stdout', [RouteFilterController::class, 'a'])->name('stdout');
    File::shouldReceive('put')->never();

    $result = (new CommandTester(Artisan::all()['scramble:export']))->run([
        '--stdout' => true,
        '--routes' => 'stdout',
    ]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getOutput())->toEndWith(PHP_EOL)
        ->and(json_decode($result->getOutput(), true)['paths'])->toHaveKey('/stdout')
        ->and($result->getErrorOutput())->toBe('');
});

it('writes stdout export diagnostics to stderr', function () {
    RouteFacade::get('api/stdout-unknown', [UnknownSchemaController::class, 'show'])->name('stdout-unknown');
    File::shouldReceive('put')->never();

    $tester = new CommandTester(Artisan::all()['scramble:export']);
    $tester->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

    $result = $tester->run([
        '--stdout' => true,
        '--routes' => 'stdout-unknown',
        '--fail-on-unknown' => true,
    ]);

    expect($result->statusCode)->toBe(Command::FAILURE)
        ->and(json_decode($result->getOutput(), true))->toBeArray()
        ->and($result->getOutput())->not->toContain('UnknownType')
        ->and($result->getErrorOutput())->toContain('Schema `UnknownType` is not allowed');
});

it('does not allow stdout and path options together', function () {
    File::shouldReceive('put')->never();

    $result = (new CommandTester(Artisan::all()['scramble:export']))->run([
        '--stdout' => true,
        '--path' => 'api-test.json',
    ]);

    expect($result->statusCode)->toBe(Command::INVALID)
        ->and($result->getOutput())->toBe('')
        ->and($result->getErrorOutput())->toContain('The --stdout and --path options cannot be used together.');
});

it('should export the documentation of the API specified by the --api option', function () {
    $api = 'v2';
    $exportPath = 'scramble/api-v2.json';
    $generator = app(Generator::class);

    Scramble::registerApi($api, [
        'api_path' => 'api/'.$api,
        'export_path' => $exportPath,
    ]);

    File::shouldReceive('put')
        ->once()
        ->with(
            $exportPath,
            json_encode($generator(Scramble::getGeneratorConfig($api)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--api' => $api,
    ])->assertOk();
});

it('should export the documentation of the API specified by the --api option without export_path config', function () {
    $api = 'v2';
    $generator = app(Generator::class);

    Scramble::registerApi($api, [
        'api_path' => 'api/v2',
        'export_path' => null,
    ]);

    File::shouldReceive('put')
        ->once()
        ->with(
            'api-'.$api.'.json',
            json_encode($generator(Scramble::getGeneratorConfig($api)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

    artisan(ExportDocumentation::class, [
        '--api' => $api,
    ])->assertOk();
});

it('fails export on unknown schemas without throwing', function () {
    Scramble::routes(fn (Route $route) => $route->uri === 'api/unknown');
    RouteFacade::get('api/unknown', [UnknownSchemaController::class, 'show']);

    File::shouldReceive('put')->once();

    artisan(ExportDocumentation::class, ['--fail-on-unknown' => true])
        ->expectsOutputToContain('with 1 error')
        ->assertFailed();
});

it('fails analysis on unknown schemas without throwing', function () {
    Scramble::routes(fn (Route $route) => $route->uri === 'api/unknown');
    RouteFacade::get('api/unknown', [UnknownSchemaController::class, 'show']);

    artisan(AnalyzeDocumentation::class, ['--fail-on-unknown' => true])
        ->expectsOutputToContain('Schema `UnknownType` is not allowed')
        ->assertFailed();
});

it('filters analyzed documentation by route name', function () {
    RouteFacade::get('api/route-filter-known', [RouteFilterController::class, 'a'])->name('route-filter.known');
    RouteFacade::get('api/route-filter-unknown', [UnknownSchemaController::class, 'show'])->name('route-filter.unknown');

    artisan(AnalyzeDocumentation::class, [
        '--routes' => 'route-filter.known',
        '--fail-on-unknown' => true,
    ])->assertOk();
});

it('does not print a success message when analysis finds no diagnostics', function () {
    RouteFacade::get('api/analyze-success', [RouteFilterController::class, 'a'])->name('analyze-success');

    $result = (new CommandTester(Artisan::all()['scramble:analyze']))->run([
        '--routes' => 'analyze-success',
    ]);

    expect($result->statusCode)->toBe(Command::SUCCESS)
        ->and($result->getOutput())->toBe('')
        ->and($result->getErrorOutput())->toBe('');
});

class RouteFilterController
{
    public function a()
    {
        return ['a' => true];
    }

    public function b()
    {
        return ['b' => true];
    }

    public function c()
    {
        return ['c' => true];
    }
}

class UnknownSchemaController
{
    public function show()
    {
        return unknown_value();
    }
}

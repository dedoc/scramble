<?php

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Support\DevTools;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Compilers\BladeCompiler;

it('uses app debug as the default dev tools setting', function () {
    config()->set('scramble.dev_tools', config('app.debug'));

    expect(DevTools::enabled())->toBe((bool) config('app.debug'));
});

it('honors an explicit dev tools setting', function () {
    config()->set('app.debug', true);
    config()->set('scramble.dev_tools', false);

    expect(DevTools::enabled())->toBeFalse();
});

it('resolves asset paths inside the package dist directory', function () {
    expect(DevTools::assetPath('devtools.js'))->toBe(
        dirname(__DIR__, 2).'/dist/devtools.js'
    );
});

it('renders the built entry without leaking its stylesheet into the document', function () {
    config()->set('scramble.dev_tools', true);
    Route::get('_scramble/dev-tools/{file}', fn () => '')->name('scramble.dev-tools.asset');
    $diagnostics = new DiagnosticsCollector;
    $diagnostics->reportQuietly(new GenericDiagnostic(DiagnosticSeverity::Error, 'Broken documentation'));
    $renderer = 'elements';
    $proNudge = new ProNudgeCollector;
    $proNudge->record(ProNudgeSignal::LaravelDataReturn, new RouteInfo(
        Route::get('api/users', fn () => []),
        'GET',
    ));
    $proNudge->record(ProNudgeSignal::LaravelDataRequest, new RouteInfo(
        Route::post('api/users', fn () => []),
        'POST',
    ));
    $proNudge->record(ProNudgeSignal::QueryBuilder, new RouteInfo(
        Route::get('api/posts', fn () => []),
        'GET',
    ));

    $html = view('scramble::dev-tools', compact('diagnostics', 'renderer', 'proNudge'))->render();

    expect($html)
        ->toContain('/_scramble/dev-tools/devtools.js')
        ->toContain('id="scramble-dev-tools-data"')
        ->toContain('"severity":"error"')
        ->toContain('"query_builder":{"count":1,"description":"1 endpoint uses Spatie Query Builder"}')
        ->toContain('"laravel_data_return":{"count":1,"description":"1 endpoint returns Laravel Data objects"}')
        ->toContain('"laravel_data_request":{"count":1,"description":"1 endpoint accepts Laravel Data objects"}')
        ->toContain('"renderer":"elements"')
        ->not->toContain('/_scramble/dev-tools/devtools.css')
        ->not->toContain('/@vite/client');
});

it('does not render assets when dev tools are disabled', function () {
    config()->set('scramble.dev_tools', false);

    expect(view('scramble::dev-tools')->render())->toBeEmpty();
});

it('serializes missing pro nudges as an empty object', function () {
    config()->set('scramble.dev_tools', true);
    Route::get('_scramble/dev-tools/{file}', fn () => '')->name('scramble.dev-tools.asset');

    $html = view('scramble::dev-tools', [
        'diagnostics' => new DiagnosticsCollector,
        'renderer' => 'elements',
    ])->render();

    expect($html)->toContain('"proNudges":{}');
});

it('compiles the vite client URL as a literal path', function () {
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/dev-tools.blade.php');
    $compiled = app(BladeCompiler::class)->compileString($template);

    expect($compiled)
        ->toContain('/@vite/client')
        ->not->toContain("app('Illuminate\\Foundation\\Vite')");
});

it('loads the official react refresh preamble from the client entry', function () {
    $entry = file_get_contents(dirname(__DIR__, 2).'/resources/js/devtools.tsx');

    expect($entry)->toStartWith("import '@vitejs/plugin-react/preamble';");
});

it('serializes diagnostics for the dev tools payload', function () {
    $diagnostics = new DiagnosticsCollector;
    $diagnostics->reportQuietly(new GenericDiagnostic(
        DiagnosticSeverity::Error,
        'Schema `Dedoc\Scramble\Support\Generator\Types\UnknownType` is not allowed.',
    ));
    $diagnostics->reportQuietly(new GenericDiagnostic(DiagnosticSeverity::Warning, 'Incomplete documentation'));

    expect($diagnostics->toArray())->toBe([
        [
            'key' => 'GEN001',
            'code' => 'GEN001',
            'severity' => 'error',
            'message' => 'Schema `UnknownType` is not allowed',
            'tip' => null,
            'details' => [],
            'context' => null,
        ],
        [
            'key' => 'GEN001',
            'code' => 'GEN001',
            'severity' => 'warning',
            'message' => 'Incomplete documentation',
            'tip' => null,
            'details' => [],
            'context' => null,
        ],
    ]);
});

it('serializes route context for grouping diagnostics', function () {
    $route = Route::patch('api/user/{user}', [DevToolsTestController::class, 'update']);
    $diagnostics = new DiagnosticsCollector;
    $diagnostics->reportQuietly(
        (new GenericDiagnostic(DiagnosticSeverity::Warning, 'Incomplete documentation'))
            ->withContext($route)
    );

    expect($diagnostics->toArray()[0]['context'])->toBe([
        'key' => 'route:PATCH:api/user/{user}:DevToolsTestController@update',
        'type' => 'route',
        'label' => '/api/user/{user}',
        'method' => 'PATCH',
        'detail' => 'DevToolsTestController@update',
    ]);
});

class DevToolsTestController
{
    public function update(): void {}
}

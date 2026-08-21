<?php

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Support\DevTools;
use Illuminate\Support\Facades\Route;

it('uses app debug as the default dev tools setting', function () {
    $scrambleConfig = config('scramble');
    unset($scrambleConfig['dev_tools']);
    config()->set('scramble', $scrambleConfig);
    config()->set('app.debug', true);

    expect(DevTools::enabled())->toBeTrue();
});

it('honors an explicit dev tools setting', function () {
    config()->set('app.debug', true);
    config()->set('scramble.dev_tools', false);

    expect(DevTools::enabled())->toBeFalse();
});

it('does not render assets when dev tools are disabled', function () {
    config()->set('scramble.dev_tools', false);

    expect(view('scramble::dev-tools')->render())->toBeEmpty();
});

it('serializes missing pro nudges as an empty object', function () {
    config()->set('scramble.dev_tools', true);
    Route::get('_scramble/dev-tools/devtools.js', fn () => '')->name('scramble.dev-tools.asset');

    $html = view('scramble::dev-tools', [
        'generator' => app(CacheableGenerator::class),
        'renderer' => 'elements',
    ])->render();

    expect($html)->toContain('"proNudges":{}');
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

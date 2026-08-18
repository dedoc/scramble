<?php

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Support\DevTools;
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

    $html = view('scramble::dev-tools', compact('diagnostics'))->render();

    expect($html)
        ->toContain('/_scramble/dev-tools/devtools.js')
        ->toContain('id="scramble-dev-tools-data"')
        ->toContain('"severity":"error"')
        ->not->toContain('/_scramble/dev-tools/devtools.css')
        ->not->toContain('/@vite/client');
});

it('does not render assets when dev tools are disabled', function () {
    config()->set('scramble.dev_tools', false);

    expect(view('scramble::dev-tools')->render())->toBeEmpty();
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
    $diagnostics->reportQuietly(new GenericDiagnostic(DiagnosticSeverity::Error, 'Broken documentation.'));
    $diagnostics->reportQuietly(new GenericDiagnostic(DiagnosticSeverity::Warning, 'Incomplete documentation'));

    expect($diagnostics->toArray())->toBe([
        [
            'key' => 'GEN001',
            'code' => 'GEN001',
            'severity' => 'error',
            'message' => 'Broken documentation',
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

it('mounts react and tailwind inside an open shadow root', function () {
    $root = dirname(__DIR__, 2);
    $entry = file_get_contents($root.'/resources/js/devtools.tsx');
    $styles = file_get_contents($root.'/resources/js/devtools.css');
    $component = file_get_contents($root.'/resources/js/DevToolsApp.tsx');
    $icons = file_get_contents($root.'/resources/js/DiagnosticIcons.tsx');
    $issuesView = file_get_contents($root.'/resources/js/IssuesView.tsx');
    $types = file_get_contents($root.'/resources/js/types.ts');

    expect($entry)
        ->toContain("import devToolsStyles from './devtools.css?inline';")
        ->toContain("attachShadow({ mode: 'open' })")
        ->toContain('shadow.append(stylesheet, container, portalTarget)')
        ->toContain('import.meta.hot.dispose')
        ->toContain("document.getElementById('scramble-dev-tools-data')")
        ->toContain('<DevToolsApp diagnostics={data.diagnostics} />')
        ->and($styles)
        ->toContain('@source "./**/*.{ts,tsx}";')
        ->toContain(':host, *, ::before, ::after, ::backdrop')
        ->toContain('--tw-inset-shadow: 0 0 #0000;')
        ->not->toContain('prefix(')
        ->not->toContain('important')
        ->and($component)
        ->toContain("severity === 'error'")
        ->toContain("severity === 'warning'")
        ->toContain('errorCount > 0 &&')
        ->toContain('warningCount > 0 &&')
        ->toContain('<ErrorIcon />')
        ->toContain('<WarningIcon />')
        ->not->toContain('scramble:')
        ->and($icons)
        ->toContain('export function ErrorIcon(')
        ->toContain('export function WarningIcon(')
        ->and($issuesView)
        ->toContain('export function IssuesView(')
        ->toContain('export function IssuesTabs(')
        ->toContain('export function IssueGroup(')
        ->toContain('export function IssueItem(')
        ->toContain("event.key === 'Escape'")
        ->and($types)
        ->toContain("export type DiagnosticSeverity = 'error' | 'warning';")
        ->toContain('export interface Diagnostic');
});

class DevToolsTestController
{
    public function update(): void {}
}

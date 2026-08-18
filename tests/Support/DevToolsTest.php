<?php

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

    $html = view('scramble::dev-tools')->render();

    expect($html)
        ->toContain('/_scramble/dev-tools/devtools.js')
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
    $entry = file_get_contents(dirname(__DIR__, 2).'/resources/js/devtools.js');

    expect($entry)->toStartWith("import '@vitejs/plugin-react/preamble';");
});

it('mounts react and tailwind inside an open shadow root', function () {
    $root = dirname(__DIR__, 2);
    $entry = file_get_contents($root.'/resources/js/devtools.js');
    $styles = file_get_contents($root.'/resources/js/devtools.css');
    $component = file_get_contents($root.'/resources/js/DevTools.jsx');

    expect($entry)
        ->toContain("import devToolsStyles from './devtools.css?inline';")
        ->toContain("attachShadow({ mode: 'open' })")
        ->toContain('shadow.append(stylesheet, container, portalTarget)')
        ->toContain('import.meta.hot.dispose')
        ->and($styles)
        ->toContain('@source "./**/*.{js,jsx}";')
        ->not->toContain('prefix(')
        ->not->toContain('important')
        ->and($component)
        ->not->toContain('scramble:');
});

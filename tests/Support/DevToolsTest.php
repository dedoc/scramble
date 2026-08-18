<?php

use Dedoc\Scramble\Support\DevTools;
use Illuminate\Support\Facades\Route;

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

it('renders built assets on documentation pages', function () {
    config()->set('scramble.dev_tools', true);
    Route::get('_scramble/dev-tools/{file}', fn () => '')->name('scramble.dev-tools.asset');

    $html = view('scramble::dev-tools')->render();

    expect($html)
        ->toContain('/_scramble/dev-tools/devtools.css')
        ->toContain('/_scramble/dev-tools/devtools.js')
        ->not->toContain('/@vite/client');
});

it('does not render assets when dev tools are disabled', function () {
    config()->set('scramble.dev_tools', false);

    expect(view('scramble::dev-tools')->render())->toBeEmpty();
});

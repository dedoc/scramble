<?php

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
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

it('serializes a missing pro nudge as null', function () {
    config()->set('scramble.dev_tools', true);
    Route::get('_scramble/dev-tools/devtools.js', fn () => '')->name('scramble.dev-tools.asset');

    $html = view('scramble::dev-tools', [
        'result' => app(CacheableGenerator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API)),
        'renderer' => 'elements',
    ])->render();

    expect($html)->toContain('"proNudge":null');
});

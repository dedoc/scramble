<?php

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

it('does not render assets when dev tools are disabled', function () {
    config()->set('scramble.dev_tools.enabled', false);

    expect(view('scramble::dev-tools')->render())->toBeEmpty();
});

it('serializes a missing pro nudge as null', function () {
    config()->set('scramble.dev_tools.enabled', true);
    Route::get('_scramble/dev-tools/devtools.js', fn () => '')->name('scramble.dev-tools.asset');

    $html = view('scramble::dev-tools', [
        'result' => app(CacheableGenerator::class)->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API)),
        'renderer' => 'elements',
    ])->render();

    expect($html)->toContain('"proNudge":null');
});

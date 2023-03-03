<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::middleware(config('scramble.middleware', [RestrictedDocsAccess::class]))->group(function () {
    Route::get('docs/api.json', function (Dedoc\Scramble\Generator $generator) {
        if (config('scramble.cache.enabled')) {
            return Cache::driver(config('scramble.cache.driver'))->remember(
                'scramble.docs.index.cache',
                (int) config('scramble.cache.ttl'),
                function () use ($generator) {
                    return $generator();
                },
            );
        }

        return $generator();
    })->name('scramble.docs.index');

    Route::view('docs/api', 'scramble::docs')->name('scramble.docs.api');
});

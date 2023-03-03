<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(config('scramble.middleware', [RestrictedDocsAccess::class]))->group(function () {
    Route::get('docs/api.json', Generator::class)->name('scramble.docs.index');

    Route::view('docs/api', 'scramble::docs')->name('scramble.docs.api');
});

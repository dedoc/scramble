<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(config('scramble.middleware', [RestrictedDocsAccess::class]))->group(function () {
    Route::get('docs/api.json', function (Dedoc\Scramble\Generator $generator) {
        return response()->json($generator(), options: JSON_PRETTY_PRINT);
    })->name('scramble.docs.index');

    Route::view('docs/api', 'scramble::docs')->name('scramble.docs.api');
});

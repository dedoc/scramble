<?php

use Illuminate\Support\Facades\Route;

Route::get('docs/api.json', function () {
    $generator = new \Dedoc\Documentor\Generator();

    return $generator();
})->name('documentor.docs.index');

Route::view('docs/api', 'documentor::docs')->name('documentor.docs.api');

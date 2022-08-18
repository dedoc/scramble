<?php

use Illuminate\Support\Facades\Route;

Route::get('docs/api.json', function () {
    $generator = new \Dedoc\ApiDocs\Generator();

    return $generator();
})->name('api-docs.docs.index');

Route::view('docs/api', 'api-docs::docs')->name('api-docs.docs.api');

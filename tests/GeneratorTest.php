<?php

namespace Dedoc\Scramble\Tests;

use Illuminate\Support\Facades\Route;

it('works with closure routes', function () {
    $openApi = generateForRoute(Route::get('api/bar', function () {
        return response()->json(['foo' => 'bar']);
    }));

//    dd($openApi);
});

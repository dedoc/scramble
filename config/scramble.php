<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    'base_url' => url('/api'),

    'info' => [
        'version' => env('API_VERSION', '0.0.1'),

        'description' => '',
    ],

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];

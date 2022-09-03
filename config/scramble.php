<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];

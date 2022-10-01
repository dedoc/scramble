---
title: Documentation config
weight: 5
---

Scramble allows to customize API base URL and OpenAPI document's `info` block by publishing a config file.

`info` block includes API version and API description. API description is rendered on the home page (`/docs/api`).

```sh
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

The content of `scramble` config:

```php
<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API server base URL: will be used as base URL in docs.
     */
    'api_base_url' => url('/api'),

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '0.0.1'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => '',
    ],

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
```

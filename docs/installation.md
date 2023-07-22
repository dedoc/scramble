---
title: Installation & setup
weight: 1
---

Scramble requires:
- PHP 8.1 or higher
- Laravel 8.x or higher

You can install Scramble via composer:

```shell
composer require dedoc/scramble
```

When Scramble is installed, 2 routes are added to your application:

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

## Models attributes support

To support model attributes/relations types in JSON resources you need to have the `doctrine/dbal` package installed.

If it is not installed, install it via composer:

```shell
composer require doctrine/dbal
```

Without the `doctrine/dbal` package, Scramble won't know the types of model attributes, so they will be shown as `string` in resulting docs.

### `Unknown database type` exception

If you get this exception, simply add a type mapping into database config, as shown in this comment: https://github.com/dedoc/scramble/issues/98#issuecomment-1374444083

## Publishing config

Optionally, you can publish the package's config file:

```sh
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

This will allow you to customize API routes resolution and OpenAPI document's details.

```sh
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

The content of `scramble` config:

```php
<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     * If you need to change this behavior, you can add your custom routes resolver using `Scramble::routes()`.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

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

    /*
     * The list of servers of the API. By default (when `null`), server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
```

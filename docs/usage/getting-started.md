---
title: Getting started
weight: 1
---

## Endpoint documentation

By default, all routes starting with `api` are added to the documentation.

To customize which routes are documented, you can either modify `scramble.api_path` config value, or you may provide your own route resolver function using `Scramble::route` in the `boot` method of a service provider. For example, in your `AppServiceProvider`:

```php
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    Scramble::routes(function (Route $route) {
        return Str::startsWith($route->uri, 'api/');
    });
}
```

Route resolver function accepts a route and return `bool` determining if the route should be added to docs or not.

## Docs authorization

Scramble exposes docs at the `/docs/api` URI. By default, you will only be able to access this route in the `local` environment.

Define `viewApiDocs` gate if you need to allow access in other environments:

```php
Gate::define('viewApiDocs', function (User $user) {
    return in_array($user->email, ['admin@app.com']);
});
```

## Documentation config

Scramble allows you to customize API path and OpenAPI document's `info` block by publishing a config file.

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

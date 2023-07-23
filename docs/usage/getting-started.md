---
title: Getting started
weight: 1
---

Now, after you have Scramble installed, first thing to do is to ensure that all the API routes are added to the docs.

## Routes resolution

By default, all routes starting with `api` are added to the documentation. For example, `yoursite.com/api/users` will be added to the docs. This can be customized by modifying `scramble.api_path` config value. For example, if you want to add all routes starting with `api/v1`, you should set `scramble.api_path` to `api/v1`. Make sure you publish the config file first.

If your API routes use a different domain, you can account for it by modifying `scramble.api_domain` config value. By default, it is set to `null` which means that the current domain will be used. So if your API routes are on `api.yoursite.com`, you should set `scramble.api_domain` to `api.yoursite.com`.

Also, you may provide your own route resolver function using `Scramble::route` in the `boot` method of a service provider. This way you can exclude routes or include just few ones. It will take precedence over the default route matching. For example, in your `AppServiceProvider`:

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

At this point your docs should be available at `/docs/api` URI.

## Docs authorization

By default, you will only be able to access `/docs/api` route in the `local` environment.

Define `viewApiDocs` gate if you need to allow access in other environments. 

For example, to allow access to the docs in the `production` environment only for the user with email `admin@app.com`, the gate would look like this:

```php
Gate::define('viewApiDocs', function (User $user) {
    return in_array($user->email, ['admin@app.com']);
});
```

## Customizing API overview page
When you visit `/docs/api` first time, the page itself is almost empty. 

You can add some content there by publishing the config file and setting `scramble.info.description` config value. Content can be as long as you want. Also, you can use markdown there, it will be rendered as HTML.

```php
return [
    // ...
    'info' => [
        // ...
        'description' => 'This is my **API** description',
    ],
    // ...
];
```

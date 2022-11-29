---
title: Servers (API domains)
weight: 1
---

OpenAPI standard allows you to specify few servers you API is available at. Scramble supports this feature and here is how you can use it.

## Multiple servers

In the published Scramble's config file you can see a key called `servers`. 

By default, it is `null` which means that Scramble will automatically generate a server based on the current application's domain and config (`scramble.api_path`, `scramble.api_domain`).

To specify servers manually, you can provide an array of available servers to the `scramble.server` config key. In this array a key is a server's description and value is server base URL. Value in array will be passed to `url()` helper to generate a final URL that will be used in OpenAPI document. So unless you use an absolute URL, your current domain will be used.

Here is an example of the config. Notice that if you use `scramble.servers` you MUST use either relative path or full absolute URL with protocol and path:

```php
'servers' => [
    'Local' => 'api',
    'Prod' => 'https://application.com/api',
],
```

## Server variables

Scramble also supports server variables. This is useful when you use a part of your domain as a parameter. For example, `{subdomain}.application.com`. `subdomain` here is a server variable.

So as an example, here are few routes defined as following:

```php
Route::domain('{subdomain}.application.com')->group(function () {
    Route::apiResource('todo-item', \App\Http\Controllers\TodoItemController::class)->only([
        'index',
        'store',
    ]);
});
```

By default, Scramble will add a server `{subdomain}.application.com` to the related paths or operations unless server variables from your route domain are defined in `scramble.api_domain` config. The same applies to servers defined in multiple servers config (`scramble.servers`): if all of them have matching server variables and path, Scramble won't add any alternative servers to the operation or path.

You can also add more description to server variables using `Scramble::defineServerVariables` in `boot` method of a service provider. For example, in your `AppServiceProvider`:

```php
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    Scramble::defineServerVariables([
        'subdomain' => ServerVariable::make(
            default: 'team1', 
            description: 'A team on which behalf a request is being made.',
        ),
    ]);
}
```

> **Note** Please note that while Scramble will generate OpenAPI document, Stoplight Elements library that Scramble uses for UI doesn't give you an ability to change server variables to try things out. As a workaround, you can set meaningful default values for your variables until UI supports them properly.

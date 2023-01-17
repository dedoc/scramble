---
title: Endpoint documentation
weight: 1
---

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

## Tags (folders)
All the endpoints are organized in folders by controller name. Endpoint organization in rendered docs is based on OpenAPI operation's tags. 

When there are a lot of controllers in your application, you will have a ton of folders, and it may be hard to navigate through that list of folders.

You can add your own tags on the controller's level using `@tags` in PhpDoc. This will put all the routes from that controller in this folder. It allows you to reduce the amount of folders in rendered docs and organize the docs in a way that makes more sense.

Multiple tags are supported: simply write them in one line separated via comma.

> **Note** Please note that the UI Scramble uses for rendering docs doesn't support nested folders. It uses the first tag as a folder. Other tags will still be there in OpenAPI documentation but won't be shown in the UI.

```php
/**
 * @tags Media
 */
class DownloadMediaController
{
   public function show(Media $mediaItem)
   {
      return $mediaItem;
   }
}
```

## General documentation
Scramble can get endpoint docs from PhpDoc comment of the route's method.

`summary` is the first row of the doc. `description` is the other text in doc. When there is only one line of text in PhpDoc it is treated as `summary`, as you can expect.

```php
/**
 * This is summary.
 * 
 * This is a description. In can be as large as needed and contain `markdown`.
 */
```

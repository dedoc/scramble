---
title: Endpoint documentation
weight: 1
---

By default, all routes with `api` middleware are added to the documentation.

You can override this behavior by providing a closure that determines if route should be added to the docs. 

```php
ApiDocs::routes(function (\Illuminate\Routing\Route $route) {
    return in_array('my-api-middleware', $route->gatherMiddleware());
});
```

## Tags (folders)
All the endpoints are organized in folders by the controller names. Endpoints organisation in rendered docs is based on OpenAPI operation's tags. 

When there are a lot of controllers in your application, you will have a ton of folders, and it may be hard to navigate through that list of folders.

You can add your own tags on the controller's level using `@tags` in PhpDoc. This will put all the routes from that controller in this folder. It allows you to reduce the amount of folders in rendered docs and organize the docs in a way that makes more sense.

Multiple tags is supported: simply write them in one line separated via comma.

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
${name} can get endpoint docs from PhpDoc comment of the route's method.

`summary` is a first row of the doc. `description` is the other text in doc. When there is only one line of text in PhpDoc it is treated as `summary`, as you can expect.

```php
/**
 * This is summary.
 * 
 * This is a description. In can be as large as needed and contain `markdown`.
 */
```

---
title: Requests
weight: 2
---

## Route params
All the params from the route are added to the docs.

For example, when you have this route:
```
PUT /todo-items/{todoItem}
```

`todoItem` is added to the docs as a route parameter. When `todoItem` is a route parameter that uses model binding, Scramble will automatically set a type to `integer` (or `string`, if UUID is used as a key) and a description will be added in format `The todo item ID`.

You can override the parameter type and description in docs by providing a PhpDoc comment to the controller's method.
```php
use App\Models\TodoItem;

class TodoItemsController
{
    /**
     * @param string $item The todo item being updated.
     */
    public function update(Request $request, TodoItem $item)
    {
        // ...
    }
}
```
This will override defaults and the `todoItem` parameter in docs will have `string` type and corresponding description from the PhpDoc.

## Body
Scramble understands the request body based on request validation rules.

This is the source of truth both for the code and for the docs.

Currently, there are 2 ways of validating requests that are understood by Scramble:

- Call to `validate` on `$request` or `$this` in controller’s method
- Call to `Validator` facade's `make` method with method call on request (`$request->?()`) as a first argument
- `rules` method on custom `FormRequest` class

```php
use App\Models\TodoItem;

class TodoItemsController
{
    public function update(Request $request, TodoItem $item)
    {
        $request->validate([
            'body' => ['required', 'string'],
            'is_complete' => 'bool',
        ]);
    }
}
```

Based on these validation rules Scramble knows that there are 2 request body params: `body` and `is_complete`.

Same applies to `Validator::make` call.

```php
use App\Models\TodoItem;
use Illuminate\Support\Facades\Validator;

class TodoItemsController
{
    public function update(Request $request, TodoItem $item)
    {
        Validator::make($request->all(), [
            'body' => ['required', 'string'],
            'is_complete' => 'bool',
        ]);
        // ...
    }
}
```

The same applies to the `rules` method in custom `FormRequest`.

### Documenting request params manually

You can add docs to your request params by adding PHPDoc block near a validation rules of the param:

```php
use App\Models\Location;

class LocationsController
{
    public function update(Request $request, Location $location)
    {
        $request->validate([
            /**
             * The location coordinates.
             * @var array{lat: float, long: float} 
             * @example {"lat": 50.450001, "long": 30.523333}
             */
            'coordinates' => 'array',
        ]);
    }
}
```

`@example` should be either a string, or valid JSON. 

You can use `@var` to re-define or clarify type inferred from validation rules. Manually defined type will always take precedence over the automatically inferred type. 

A simple PHP comment before a param will also be used as a request body parameter description:
```php
use App\Models\TodoItem;

class TodoItemsController
{
    public function update(Request $request, TodoItem $item)
    {
        $request->validate([
            // Whether the task is complete.
            'is_complete' => 'bool',
        ]);
    }
}
```

### Rules evaluation caveats

It is important to keep in mind that rules are evaluated to be analyzed: `rules` method is called when there is a custom request class and the array with rules passed to the `validate` is evaluated as well.

This adds not obvious benefits to the resulting documentation when `Rule::in` validation rule is being represented as `enum` with all possible values in the docs.

But also it requires a developer to write rules in certain way when using validation via `validate` method call in controller. Only these expressions can be evaluated correctly:

- Using variables passed to the controller
- Static calls to classes
- Using global functions (`app()`)

Declaring local variable in method before calling `validate` and using it there will cause an error.

### Supported rules
- `required`
- `string`
- `bool`, `boolean`
- `number`
- `int`, `integer`
- `array`
- `in`, `Rule::in`
- `nullable`
- `email`
- `uuid`
- `exists` (marks value as `int` if attribute name is either `id` or `*_id`)
- `min` (numeric types only)
- `max` (numeric types only)
- `Enum`
- `confirmed`

## Adding title and description

Scramble can get endpoint docs from PhpDoc comment of the route's method.

`summary` is the first row of the doc. `description` is the other text in doc. When there is only one line of text in PhpDoc it is treated as `summary`, as you can expect.

```php
/**
 * This is summary.
 * 
 * This is a description. In can be as large as needed and contain `markdown`.
 */
```

## Organizing in folders
All the endpoints are organized in folders by controller name. Endpoint organization in rendered docs is based on OpenAPI operation's tags.

When there are a lot of controllers in your application, you will have a ton of folders, and it may be hard to navigate through that list of folders.

You can add your own tags on the controller's level using `@tags` in PhpDoc. This will put all the routes from that controller in this folder. It allows you to reduce the amount of folders in rendered docs and organize the docs in a way that makes more sense.

Multiple tags are supported: simply write them in one line separated via comma.

<x-alert>
<x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg></x-slot>
Please note that the UI Scramble uses for rendering docs doesn't support nested folders. It uses the first tag as a folder. Other tags will still be there in OpenAPI documentation but won't be shown in the UI.
</x-alert>

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

## Manually providing operation ID
Scramble generates unique operation ID for your endpoints. It is based on the route name. If route name is not unique, Scramble will use controller and method name and will create a unique ID based on that.

You always can override operation ID by adding `@operationId` to the route's method PhpDoc.

```php
class DownloadMediaController
{
    /**
     * @operationId getMediaItem
     */
     public function show(Media $mediaItem)
     {
         return $mediaItem;
     }
}
```

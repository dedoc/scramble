---
title: Responses
weight: 3
---

Scramble generates documentation for endpoint responses by analyzing the source code of the controller's method. It examines the inferred return type, calls to various methods (e.g., `validate` or `authorize`), and other statements to identify the different types of responses that an endpoint may produce.

Currently, these types will be automatically documented as responses:

- API resources (instances of classes extending `JsonResource`)
- Collections of API resources (both anonymous collections and dedicated classes)
- Some `response()` (`ResponseFactory`) support: `response()->make(...)`, `response()->json(...)`, `response()->noContent()`
- `JsonResponse` and `Response` instances
- Models
- Simple types: arrays, strings, numbers, etc.

Paginated responses (`LengthAwarePaginator`) are also supported, but they need to be documented in PhpDoc for now.

## On type inference
Scramble uses type inference based on source code analysis to automatically determine the data types of variables and expressions in the source code without explicitly specifying them. This means that Scramble can understand what type of data (e.g., strings, numbers, objects) each part of the code is working with, even if the code does not explicitly declare it.

With this technique, Scramble can figure out the likely data types that a controller's method will return without needing extra explanations. It looks at how the method processes data, the methods it uses, and other hints to infer the possible return types.

This process allows Scramble to generate comprehensive and accurate documentation for the API. It also reduces the need to manually document the responses, making the documentation process more efficient and less error-prone.

### Types priority

Scramble extracts controller’s method return type based on the following priority:

1. PhpDoc comment (`@response` tag) - gives ability to document arbitrary response types
2. Typehint - if the method has a typehint and doesn't have a PhpDoc comment, the typehint will be used
3. Code return statements - if the method doesn't have a PhpDoc `@response` tag, and inferred return type is more specific than typehint, the inferred type will be used

```php
use App\Models\TodoItem;

class TodoItemsController
{
    /**
     * @response TodoItemResource // 1 - PhpDoc
     */
    public function update(Request $request, TodoItem $item): TodoItemResource // 2 - typehint
    {
        return new TodoItemResource($item); // 3 - code inference
    }
}
```

This is implemented in this way so if needed, you can override the inferred type with a typehint or PhpDoc comment.

## Manual hinting in PhpDoc

Also, the response can be documented in PhpDoc using `@response` tag. This is useful when you notice that the inferred type is not correct or you want to document a custom response type.

Read more about the proper PhpDoc type syntax in [PhpStan docs](https://phpstan.org/writing-php-code/phpdoc-types).

```php
use App\Models\TodoItem;

class TodoItemsController
{
    /**
     * List available todo items.
     *
     * @response array{data: TodoItemResource[], meta: array{permissions: bool}}
     */
    public function index(Request $request)
    {
        return app(TodoService::class)->listAll();
    }
}
```

## API resources

To be properly analyzable API resource classes must return an array **node** from `toArray` method.

This will work:

```php
class TodoItemResource extends JsonResource
{
    public function toArray
    {
        return [
            'id' => $this->id,
        ];
    }
}
```

This won’t:

```php
class TodoItemResource extends JsonResource
{
    public function toArray
    {
        return array_merge(parent::toArray(), [
            'id' => $this->id,
        ]);
    }
}
```

### Model resolution

All resource property types that are accessed on `$this` or `$this->resource` will be resolved from the corresponding model attributes.

<x-alert>
<x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M10 1a6 6 0 00-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.644a.75.75 0 00.572.729 6.016 6.016 0 002.856 0A.75.75 0 0012 15.1v-.644c0-1.013.762-1.957 1.815-2.825A6 6 0 0010 1zM8.863 17.414a.75.75 0 00-.226 1.483 9.066 9.066 0 002.726 0 .75.75 0 00-.226-1.483 7.553 7.553 0 01-2.274 0z" /></svg>
</x-slot:icon>
You need to have `doctrine/dbal` package installed for this to work.
</x-alert>

By default, Scramble tries to find a model in `App\\Models\\` namespace, based on resource name. Before the lookup resource name is converted to singular form (`TodoItemsResource` → `TodoItem`).

You can provide the name of the model manually in case you have your custom models namespaces by adding PhpDoc to the resource class. You can either document `$resource` property type, or define the model as a `@mixin`:

```php
use App\Domains\Todo\Models\TodoItem;

/**
 * @property TodoItem $resource
 */
class TodoItemResource extends JsonResource
{
    public function toArray
    {
        return [/*...*/];
    }
}
```

And `@mixin` example:

```php
use App\Domains\Todo\Models\TodoItem;

/**
 * @mixin TodoItem
 */
class TodoItemResource extends JsonResource
{
    public function toArray
    {
        return [/*...*/];
    }
}
```
<x-alert>
<x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg></x-slot>
If model for resource is not found, all the fields will have `string` type in docs.
</x-alert>

### Automatically supported fields types

- Model attribute type (`$this->id`, `$this->resource->id`)

```php
return [
    'id' => $this->id,
    'content' => $this->resource->content,
];
```

- Nested API resources

```php
return [
    'list' => new ListResource($this->list),
];
```

- Conditional merging of nested API resources

```php
return [
    'list' => new ListResource($this->whenLoaded('list')),
];
```

- Arrays

```php
return [
    'list' => [
        'name' => $this->list_name,
    ],
];
```

- Conditional merging of the arrays in the response:
    - `merge`
    - `mergeWhen`
    - `when`

All the conditional fields will be marked as optional in resulting docs.

Also, API resources are stored in OpenAPI docs as reference and usage of the resources across other resources will be rendered as using schema reference in docs.

### Manually describing the fields

For the cases when automatic field type resolution is not working, or you need to add a description to the field, you can add PhpDoc comment:

```php
return [
    'id' => $this->id,
    /** @var string $content The content of todo item, truncated. */
    'content' => $this->someMethodCall(),
];
```

Note, that you need to add var name when you need to add description. You don't need it if it is only type.

You can use other resource classes or more complex types to document more complex structures:

```php
return [
    'id' => $this->id,
    /** @var array<string, ThreadResource> */
    'threads' => $this->threads->keyBy('name')->mapInto(ThreadResource::class),
];
```

This is the format of type annotations used by PhpStan. You can read more about it here: https://phpstan.org/writing-php-code/phpdoc-types.

## API resources collection

This sort of response can be automatically analyzed from code return statement.

If you need some code manipulation on the object and it cannot be automatically inferred, you can add the type manually.

To manually add this response type you add this to your PhpDoc like this:

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\TodoItem;

class TodoItemsController
{
    /**
     * List available todo items.
     *
     * @response AnonymousResourceCollection<TodoItemResource>
     */
    public function index(Request $request)
    {
        return TodoItemResource::collection(TodoItem::all());
    }
}
```

### Additional collection's data
Scramble will automatically document additional data that is added to the collection using `additional` method.

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\TodoItem;
use Illuminate\Pagination\LengthAwarePaginator;

class TodoItemsController
{
    public function index(Request $request)
    {
        return TodoItemResource::collection(TodoItem::all())
            ->additional(['permissions' => true]);
    }
}
```


## Models

When you don't have a resource and simply return a model from controller's method, Scramble will be able to document that as well.

When documenting models, Scramble will document what the model's `toArray` returns. So if `toArray` is overridden, it should return an array to be properly analyzed.

```php
public function show(Request $request, User $user)
{
    return $user;
}
```
<x-alert>
<x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg></x-slot>
Please note that if you don't type annotate a method's return type, the model type should be specified in arguments list. Otherwise, Scramble won't be able to infer the returned variable type and document it properly. Also, now only relations that are always present on a model (`$with` property) are documented.
</x-alert>

## Paginated responses

Paginated response cannot be inferred from code automatically so you need to typehint it manually in PhpDoc like so:

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\TodoItem;
use Illuminate\Pagination\LengthAwarePaginator;

class TodoItemsController
{
    /**
     * List available todo items.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<TodoItemResource>>
     */
    public function index(Request $request)
    {
        return TodoItemResource::collection(TodoItem::paginate()->get());
    }
}
```

This will add all the meta and other information to the docs.

## Error response

By analyzing the codebase Scramble can not only understand OK responses, but error responses as well. Here are the cases which Scramble understand and can document.  

### Call to `validate` and `authorize` in controller

Calls to `validate` and `authorize` will be documented as corresponding error responses in resulting docs: responses with `422` and `403` statuses respectively.

### Model binding in route

When model binding is used, Scramble adds possible `404` error response to the documentation. This is the case when a model is not found. 

### Abort helpers 

When using abort helpers (`abort`, `abort_if`, `abort_unless`), Scramble will document the resulting response.

Currently only numeric `code` is supported. Message content (`message`) will be documented as an example in resulting docs.

For example, this line of code:

```php
abort(400, 'This case is not supported'); 
```

Corresponds to the error response in documentation with code `400` and `message` property with an example (`This case is not supported`).

## Response description

Description can be added to the response by adding a comment right before the `return` in a controller.
```php
public function show(Request $request, User $user)
{
    // A user resource.
    return new UserResource($user);
}
```

Response status and type can be added manually as well using `@status` and `@body` tags in PhpDoc block before the `return`.
```php
public function create(Request $request)
{
    /**
     * A user resource.
     * 
     * @status 201
     * @body User
     */
    return User::create($request->only(['email']));
}
```


---
title: Responses
weight: 3
---

Based on source code analysis, ${name} can generate endpoint responses documentation. Currently, these response types documentation is supported automatically:

- `JsonResource` response
- `AnonymousResourceCollection` of `JsonResource` items
- `LengthAwarePaginator` of `JsonResource` items

First two can be understood automatically from the code. The latest one needs to be documented in PhpDoc for now.

```php
use App\Models\TodoItem;

class TodoItemsController
{
    /**
     * @return TodoItemResource // 1 - PhpDoc
     */
    public function update(Request $request, TodoItem $item): TodoItemResource // 2 - typehint
    {
        return new TodoItemResource($item); // 3 - code inferring
    }
}
```

${name} extracts controller’s method return type based on priority:

1. PhpDoc comment
2. Typehint
3. Code return statements

This is implemented in this way because by its nature PHP is very dynamic language and Laravel uses a lot of its magic. So it can be pretty challenging to figure out method’s response type reliably in 100% cases without developing another PhpStan library.

So while ${name} ultimate goal is to handle all possible return types automatically, for now I’ve decided to build it for the most popular things first.

Automatic response definition from code return statements for now are limited to these 2 cases:

1. `return new JsonResource(...);` statement
2. `return JsonResource::collection(...);` statement

### Manual hinting in PhpDocs

Also, return type of the method (and hence response type) can be written in PhpDoc `@return` tag with various response types including arbitrary array shape.

## JsonResource

To be properly analyzable JsonResources must return an array **node** from `toArray` method.

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

All resource property types that are accessed on `$this` or `$this->resource` are trying to be resolved from the corresponding model attributes.

<aside>
💡 You need to have `doctrine/dbal` package installed for this to work.

</aside>

By default, ${name} tries to find a model in `App\\Models\\` namespace, based on resource name. Before the lookup resource name is converted to singular form (`TodoItemsResource` → `TodoItem`).

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

If model for resource is not found, all the fields will have `string` type in docs.

### Automatically supported fields types

- Model attribute type (`$this->id`, `$this->resource->id`)

```php
return [
    'id' => $this->id,
    'content' => $this->resource->content,
];
```

- Nested JsonResource

```php
return [
    'list' => new ListResource($this->list),
];
```

- Conditional merging of nested JsonResource

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

Also, `JsonResource` is stored in OpenAPI docs as reference and usage of the resources across the resources will be rendered as using schema reference in docs.

### Manually describing the fields

For the cases when automatic field type resolution is not working or you need to add a description to the field, you can add PhpDoc comment:

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

This is the format of type annotations used by PhpStan. You can read more about it here: [https://phpstan.org/writing-php-code/phpdoc-types](https://phpstan.org/writing-php-code/phpdoc-types)

## AnonymousResourceCollection

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
     * @return AnonymousResourceCollection<TodoItemResource>
     */
    public function index(Request $request)
    {
        return TodoItemResource::collection(TodoItem::all());
    }
}
```

## LengthAwarePaginator response

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

## Arbitrary response types

If nothing from above works, and something custom needed, you can use PhpStan PhpDoc format and document return type of the method:

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\TodoItem;
use Illuminate\Pagination\LengthAwarePaginator;

class TodoItemsController
{
    /**
     * List available todo items.
     *
     * @return array{data: TodoItemResource[], meta: array{permissions: bool}}
     */
    public function index(Request $request)
    {
        return TodoItemResource::collection(TodoItem::all())
            ->additional(['permissions' => true]);
    }
}
```

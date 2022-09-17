---
title: Request body and params
weight: 2
---
## Route params
All the params from the route are added to the docs.

For example, when you have this route:
```
PUT /todo-items/{todoItem}
```

`todoItem` is added to the docs as a route parameter. When `todoItem` is a route parameter that uses model binding, Scramble will automatically set a type to `integer` and a description will be added in format `The todo item ID`.

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
This will override defaults and `todoItem` parameter in docs will have `string` type and corresponding description from the PhpDoc.

## Body
Scramble understands request body based on request validation rules.

This is the source of truth both for the code and for the docs.

Currently, there are 2 ways of validating requests that are understood by Scramble:

- Call to `validate` on `$request` or `$this` in controller’s method
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

The same applies to the `rules` method in custom `FormRequest`.

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
- `exists` (required value to be `int`)
- `min` (numeric types only)
- `max` (numeric types only)

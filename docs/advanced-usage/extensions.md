---
title: Extensions
weight: 1
---
# Extensions

To add custom behavior to the Scramble, you can create extensions.

There are 3 types of extensions:

- OpenAPI operation extension: for describing routes in docs;
- Type to OpenAPI schema extension: for describing PHP types as OpenAPI schema objects;
- Type inferring extension: to help Scramble better understand types in code.

## OpenAPI operations extension

These are the extensions that allows to add information from route to this route’s documentation. Each route is an operation in OpenAPI standard, so here you can add and shape information about the operation from the code you have.

The extension’s function accepts 2 arguments, first is an instance of operation and second one is route info object.

You add information to the operation by mutating the object.

The example of such an extension is an ability to add 403 response, if there is a call to `$this->authorize` in the controller’s method. To implement it, you need to get method’s AST from route info and look for this call there. If it is there, there might be possible 403 response, so it can be added to the operation.

## Type to OpenAPI schema extension

These extensions are needed to tell the Scramble how different types look like when represented in JSON/OpenAPI schema. For example, to PHP’s type `int` represented in JSON schema look like `{"type": "integer"}`. To have this logic in place, type to OpenAPI schema extension is used.

The extension’s function accepts 1 argument — the type. The function must return either `null`, if the extension should not handle the type, or new OpenAPI schema type otherwise.

The more useful example is `JsonResource` response. When `JsonResource` response is returned from the controller you would want to know, how it will look like in the response. And to know that, you need to know how to represent `JsonResource` as an OpenAPI schema.

So you’ll need to implement this extension in case you have some custom responsable objects. Or in case Scramble doesn’t cover your use case, the custom extension can cover it as well.

## Type inferring extension

The power of Scramble is that it can generate OpenAPI docs without forcing you to annotate everything. It simply allows you to focus on code and not on the annotations.

The system powering this idea is types inferrer that comes with Scramble. It can resolve types in code so they can be represented in the documentation.

Because of PHP and Laravel being very dynamic (and type inferrer young), the inferring system may need help to correctly get the type. Or to have a type with some extra information added.

For example, consider `optional` helper. It accepts any nullable object and allows you to call any method or property on it. If it was `null`, any call will return `null` as well. But if it wasn’t, the result of the method call/property fetch on the original object will be returned. This is pretty dynamic behavior, so to help type inferrer to analyze and understand it correctly, we will need to add an extension.

Type inferring extension function’s accept 2 arguments — the AST node being analyzed and the scope the node is currently in. The function should return `null`, if extension shouldn’t handle the type. Otherwise, it should return the resulting type for this node. For example, if node is `1`, the resulting type for it is `int`.

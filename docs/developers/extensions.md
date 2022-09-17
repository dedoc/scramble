---
title: Extensions
weight: 1
---
Imagine you've installed some awesome package that you use in your API and you want the documentation to correctly describe your API. As a good example, it can be Spatie's Laravel Query Builder package. This package will allow you to build Eloquent queries from API requests. But also the code you write will determine a shape and form of the incoming request.

To add support of custom packages (or your own ones) to the Scramble, you can create extensions.

There are 3 types of extensions:

- OpenAPI operation extension: for describing routes in docs;
- Type to schema extension: for describing PHP types as OpenAPI schema objects;
- Type inferring extension: to help Scramble better understand types in code.

Extension is a class implementing extension's interface. 

To register an extension, you add the class name to the `scramble.extensions` config's array:

```php
<?php

return [
    'middleware' => [/*...*/],

    'extensions' => [
        YourCustomExtension::class,
    ],
];
```

## OpenAPI operations extension

These are the extensions that allows to add information from route to this route’s documentation. Each route is an operation in OpenAPI standard, so here you can add and shape information about the operation from the code you have.

Every operation extension must extend `\Dedoc\Scramble\Extensions\OperationExtension` class and implement `handle` method. The method accepts 2 arguments, first is an instance of operation and second one is route info object.

You can add information to the operation by mutating the object (see methods on `Operation` class).

The example of such an extension is an ability to add 403 response, if there is a call to `$this->authorize` in the controller’s method. To implement it, you need to get method’s AST from route info and look for this call there. If it is there, there might be possible 403 response, so it can be added to the operation (see available methods on `RouteInfo` class).

## Type to schema extension

These extensions are needed to tell the Scramble how different types look like when represented in JSON/OpenAPI schema. For example, to PHP’s type `int` represented in JSON schema look like `{"type": "integer"}`. To have this logic in place, type to schema extension is used.

To create this type of extension, create a class extending the class `Dedoc\Scramble\Extensions\TypeToOpenApiSchemaExtension`. Then, you need to implement a method `shouldHandle` that will decide if the extension should handle the `Type`.

In the extension, you can use `$this->infer`, `$this->openApiTransformer`, and `$this->components` to analyze the types.

For example, consider `JsonResource` class. It can be referenced in some other OpenAPI type, or it may be returned from the endpoint.

So, to start we need to check the type and make sure that the type should be checked.

```php
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Illuminate\Http\Resources\Json\JsonResource;
use Dedoc\Scramble\Support\Type\ObjectType;

class JsonResourceOpenApi extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType 
            && $type->isInstanceOf(JsonResource::class);
    }
    
    //...
}
```

Then, we can implement the method `toSchema` that will describe how the type should be rendered in the OpenAPI. When serializing JsonResource to array, `toArray` method call result is used. So the simplest implementation for JsonResource looks like this (in reality it is a bit more complex, as there are merge values being serialized):

```php
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Illuminate\Http\Resources\Json\JsonResource;
use Dedoc\Scramble\Support\Type\ObjectType;

class JsonResourceOpenApi extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type) {/*...*/}
    
    public function toSchema(ObjectType $type)
    {
        $type = $this->infer->analyzeClass($type->name);
        
        $array = $type->getMethodCallType('toArray');
        
        return $this->openApiTransformer->transform($array);
    }
}
```

Also, when type should be used as an OpenAPI reference, you can implement `reference` method. When it is implemented, object's schema will be analyzed only once and will be saved in OpenAPI document components. When type is referenced, this reference will be used. 

To implement how types look like when they returned as responses, `toResponse` method can be implemented.

## Type inferring extension

The power of Scramble is that it can generate OpenAPI docs without forcing you to annotate everything. It simply allows you to focus on code and not on the annotations.

The system powering this idea is types inferrer that comes with Scramble. It can resolve types in code so they can be represented in the documentation.

Because of PHP and Laravel being very dynamic (and type inferrer young), the inferring system may need help to correctly get the type. Or to have a type with some extra information added.

For example, consider `optional` helper. It accepts any nullable object and allows you to call any method or property on it. If it was `null`, any call will return `null` as well. But if it wasn’t, the result of the method call/property fetch on the original object will be returned. This is pretty dynamic behavior, so to help type inferrer to analyze and understand it correctly, we will need to add an extension.

Type inferring extension function’s accept 2 arguments — the AST node being analyzed and the scope the node is currently in. The function should return `null`, if extension shouldn’t handle the type. Otherwise, it should return the resulting type for this node. For example, if node is `1`, the resulting type for it is `int`.

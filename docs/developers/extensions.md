---
title: Extensions
weight: 1
---
<x-alert>
<x-slot:icon>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
<path fill-rule="evenodd" d="M8.5 3.528v4.644c0 .729-.29 1.428-.805 1.944l-1.217 1.216a8.75 8.75 0 013.55.621l.502.201a7.25 7.25 0 004.178.365l-2.403-2.403a2.75 2.75 0 01-.805-1.944V3.528a40.205 40.205 0 00-3 0zm4.5.084l.19.015a.75.75 0 10.12-1.495 41.364 41.364 0 00-6.62 0 .75.75 0 00.12 1.495L7 3.612v4.56c0 .331-.132.649-.366.883L2.6 13.09c-1.496 1.496-.817 4.15 1.403 4.475C5.961 17.852 7.963 18 10 18s4.039-.148 5.997-.436c2.22-.325 2.9-2.979 1.403-4.475l-4.034-4.034A1.25 1.25 0 0113 8.172v-4.56z" clip-rule="evenodd" />
</svg>
</x-slot>
Extensions API is a subject to frequent change before the first stable release. With more feedback from the community, it becomes clear what is the best API for extensions. So, if you create an extension, please be ready to update it to the latest API when package version is updated.
</x-alert>

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

These are the extensions that allow you to add information about a route to the route’s documentation. Each route is an operation in the OpenAPI standard, so here you can add and shape information about the operation from the code you have.

Every operation extension must extend `\Dedoc\Scramble\Extensions\OperationExtension` class and implement `handle` method. The method accepts 2 arguments, first is an instance of operation and second one is route info object.

You can add information to the operation by mutating the object (see methods on `Operation` class).

The example of such an extension is an ability to add 403 response, if there is a call to `$this->authorize` in the controller’s method. To implement it, you need to get method’s AST from route info and look for this call there. If it is there, there might be possible 403 response, so it can be added to the operation (see available methods on `RouteInfo` class).

Here is the real example of such an extension that documents response type of the route: https://github.com/dedoc/scramble/blob/main/src/Support/OperationExtensions/ResponseExtension.php

## Type to schema extension

These extensions are needed to tell the Scramble how different types look like when represented in JSON/OpenAPI schema. For example, PHP’s type `int` is represented in JSON schema like `{"type": "integer"}`. To have this logic in place, type to schema extension is used.

To create this type of extension, create a class extending the class `Dedoc\Scramble\Extensions\TypeToOpenApiSchemaExtension`. Then, you need to implement a method `shouldHandle` that decides if the extension should handle the given type.

In the extension, you can use `$this->infer`, `$this->openApiTransformer`, and `$this->components` to analyze the types.

For example, consider the `JsonResource` class. It can be referenced in some other OpenAPI type, or it may be returned from the endpoint.

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

Then, we can implement the method `toSchema` that will describe how the type should be rendered in the OpenAPI. When serializing a JsonResource to an array, the `toArray` method call result is used. So the simplest implementation for JsonResource looks like this (in reality it is a bit more complex, as there are merge values being serialized):

```php
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Illuminate\Http\Resources\Json\JsonResource;
use Dedoc\Scramble\Support\Type\ObjectType;

class JsonResourceOpenApi extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type) {/*...*/}
    
    public function toSchema(ObjectType $type)
    {
        $this->infer->analyzeClass($type->name);
        
        $array = $type->getMethodDefinition('toArray')
            ->type
            ->getReturnType();
        
        return $this->openApiTransformer->transform($array);
    }
}
```

Also, when a type should be used as an OpenAPI reference, you can implement `reference` method. When it is implemented, the object's schema will be analyzed only once and will be saved in OpenAPI document components. When the type is referenced, this reference will be used. 

To implement how types look like when they are returned as responses, `toResponse` method can be implemented.

Here is the real implementation of the type to schema extension that adds support for `JsonResource`: https://github.com/dedoc/scramble/blob/main/src/Support/TypeToSchemaExtensions/JsonResourceTypeToSchema.php

## Type inferring extension
These extensions can be used to help Scramble type inference system to understand a type of expression node in AST.

Scramble comes with type inference system that is responsible for inferring types in the codebase for every variable, function, method, etc. Inferred types are used to generate API documentation. For example, the return type of the controller’s method is inferred and then used as a response type in the OpenAPI document.

Because of PHP and Laravel being very dynamic, the type inference system may need help to correctly get the type. Or to have a type with some extra information added.

For example, consider the `optional` helper. It accepts any nullable object and allows you to call any method or property on it. If it was `null`, any call will return `null` as well. But if it wasn't, the result of the method call/property fetch on the original object will be returned. This is pretty dynamic behavior, so to help Scramble to analyze and understand it correctly, we will need to add an extension.

Type inferring extension functions accept 2 arguments — the AST node being analyzed and the scope the node is currently in. The function should return `null`, if the extension shouldn’t handle the type. Otherwise, it should return the resulting type for this node. For example, if node is `1`, the resulting type for it is `int`.

Here is the real implementation of the type inferring extension that adds support for `response()->json(...)` and other `ResponseFactory` functions: https://github.com/dedoc/scramble/blob/main/src/Support/InferExtensions/ResponseFactoryTypeInfer.php

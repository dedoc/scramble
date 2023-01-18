---
title: How it works
weight: 2
---
The package uses static code analysis and existing Laravel conventions to generate API documentation without forcing you to write annotations. Annotations are still useful where you want to add some descriptions, or you see that Scramble cannot infer types fully – you can help it as well.

Here is a brief description of how the docs are generated.

## Requests
To generate docs for the requests, Scramble analyzes the rules used for validation of the request and route parameters. It can easily extract rules from `FormRequest` classes by looking at `rules` method. 

If a custom request class is not used, the package will look for a call to `validate` in a controller's method and will use rules from there **by evaluating** the rules array code.

## Responses
When analyzing responses, Scramble tries to figure out the response type of your controller's method. It uses pretty naїve static code analysis to determine what is returned from the controller. In most cases it should get you covered. 

Especially in cases you could've directly seen in Laravel documentation (here comes "convention" part of "how):

- `JsonResource` (`return new TodoItemResource($item);`)
- `AnonymousResourceCollection` of `JsonResource` items (`return TodoItemResource::collection($items);`)
- `LengthAwarePaginator` of `JsonResource` items (this need to be manually typehinted in PhpDoc for now)

`doctrine/dbal` package allows to get the types of model attributes, so `JsonResource` based responses are properly documented.

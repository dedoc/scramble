---
title: How it works
weight: 2
---

The package heavily relies on the existing Laravel conventions and uses them, so it can generate most of the documentation correctly without your help. Here is a brief description of how the docs is generated.

## Requests
To generate docs for the requests, Scramble analyzes the rules used for validation of the request and route parameters. It can easily extract rules from `FormRequest` classes by looking at `rules` method. 

If a custom request class is not used, the package will look a call to `validate` method in controller's method and will use rules from there **by evaluating** the rules array code.

## Responses
As Scramble relies on conventions, it currently supports these response types:
- `JsonResource`
- `AnonymousResourceCollection` of `JsonResource` items
- `LengthAwarePaginator` of `JsonResource` items

For analysing responses, nothing is being evaluated and only AST analysis is used.

`doctrine/dbal` allows to get the types of model attributes, so `JsonResource` based responses are properly documented.

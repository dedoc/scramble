---
title: How it works
weight: 3
---

${name} generates docs for your API in OpenAPI 3.1.0 format automatically from your code. 

The main motto of the project is generating your API documentation without requiring you to annotate your code. 

This allows you to focus on code and avoid annotating every possible param/field as it may result in outdated documentation. By generating docs automatically from the code your API will always have up-to-date docs which you can trust.

## How

The package heavily relies on the existing Laravel conventions and uses them, so it can generate most of the documentation correctly without your help. Here is a brief description of how the docs is generated.

## Requests
To generate docs for the requests, ${name} analyzes the rules used for validation of the request and route parameters. It can easily extract rules from `FormRequest` classes by looking at `rules` method. 

If a custom request class is not used, the package will look a call to `validate` method in controller's method and will use rules from there **by evaluating** the rules array code.

## Responses
As ${name} relies on conventions, it currently supports these response types:
- `JsonResource`
- `AnonymousResourceCollection` of `JsonResource` items
- `LengthAwarePaginator` of `JsonResource` items

For analysing responses, nothing is being evaluated and only AST analysis is used.

`doctrine/dbal` allows to get the types of model attributes, so `JsonResource` based responses are properly documented.

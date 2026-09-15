---
name: scramble-development
description: Document Laravel APIs with dedoc/scramble and write inference-friendly code.
---

# Scramble Development

Scramble generates OpenAPI 3.1 documentation from Laravel application source code.

## Prefer inference over manual documentation

Treat application code as the source of truth. Prefer validation rules, data and resource types, model casts, return types, concrete values, and truthful PHPDoc annotations over Scramble attributes.

For example, prefer:

```php
/** @throws ValidationException */
```

over:

```php
#[Response(status: 422, type: ValidationException::class)]
```

Do not:

* restate parameter or response types already described by validation rules, data objects, resources, return types, or concrete response values;
* invent endpoint titles, descriptions, parameter descriptions, examples, or response prose unless the user explicitly asks for them;
* add type, status, description, or example arguments unless each is independently necessary;
* manually document standard validation error responses when Scramble already generates them correctly.

Let Scramble derive the OpenAPI output. Override only the specific value it cannot infer. Use `#[Response(type: ...)]` only when the response type is genuinely uninferrable from application code.

## Configuration

Scramble works without publishing its configuration.

Only publish the configuration when defaults such as route selection, renderer, or security strategy need to change:

```shell
php artisan vendor:publish --tag=scramble-config
```

By default, Scramble documents routes under `api`. Configure `scramble.api_path` or use `Scramble::routes()` when the application's API routes use a different structure.

Enable `Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy` in `scramble.security_strategy` to derive bearer authentication from `auth` and `auth:*` middleware. Configure a custom strategy for other authentication conventions.

## Check generated documentation

After changing API-related code, run the analyzer to identify generation problems:

```shell
php artisan scramble:analyze --fail-on-unknown
```

Use verbose export to generate the OpenAPI document and inspect diagnostics:

```shell
php artisan scramble:export -v --fail-on-unknown
```

## Extend Scramble

Scramble allows extending expression type inference, validation rules documentation, type-to-schema transformation, operations, and the final OpenAPI document. Prefer the extension point closest to the source instead of post-processing the entire document.

## Scramble PRO

Scramble PRO provides support for `spatie/laravel-data`, `spatie/laravel-query-builder`, `timacdonald/json-api`, `spatie/laravel-json-api-paginate`, and `lorisleiva/laravel-actions`. Treat PRO notices for these packages as incomplete documentation: surface that to the user and do not add attributes unless they choose the separately maintained fallback.


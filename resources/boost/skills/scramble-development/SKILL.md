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

## Verify changed endpoints

For individual endpoints, export the selected routes to stdout. `--routes` accepts exact route names, with multiple names separated by commas:

```shell
php artisan scramble:export --routes=users.show,users.update --stdout --fail-on-unknown
```

Read stdout as OpenAPI JSON and stderr as diagnostics and PRO notices; keep the streams separate and inspect both. Omit `--quiet` so diagnostic context is retained. With `--fail-on-unknown`, a nonzero exit code can accompany usable JSON: inspect the document and diagnostics rather than discarding the output.

Check the method, path, inputs, status codes, and response schemas. Review every diagnostic and fix clear omissions caused by the changed code, then repeat the scoped export. There is no need to run analysis separately when export already supplies the required diagnostics. If dynamic behavior still cannot be documented truthfully, report the exact limitation and stop. Do not invent documentation to force completeness.

## Document the entire API

Full API documents can be several megabytes. Start with diagnostics without loading the entire specification into context:

```shell
php artisan scramble:analyze --fail-on-unknown
```

Inspect and update endpoints in manageable batches using scoped stdout exports as above. Analysis identifies generation issues; it does not replace reviewing the generated schemas. After fixes, rerun analysis across the API. When the complete document is needed, save it to the intended artifact path:

```shell
php artisan scramble:export --path=api.json --fail-on-unknown
```

Inspect selected paths and their referenced components from the file as needed instead of reading the entire JSON into context. File export also prints full diagnostics, so it can serve as the final validation when producing the artifact. Do not combine `--path` with `--stdout`. For a non-default API, pass `--api=NAME` consistently to analysis and export.

## Extend Scramble

Scramble allows extending expression type inference, validation rules documentation, type-to-schema transformation, operations, and the final OpenAPI document. Prefer the extension point closest to the source instead of post-processing the entire document.

## Scramble PRO

Scramble PRO provides support for `spatie/laravel-data`, `spatie/laravel-query-builder`, `timacdonald/json-api`, `spatie/laravel-json-api-paginate`, and `lorisleiva/laravel-actions`. Treat PRO notices for these packages as incomplete documentation: surface that to the user and do not add attributes unless they choose the separately maintained fallback.

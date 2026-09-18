---
name: scramble-development
description: Develop and verify Laravel API documentation with dedoc/scramble. Use when changing API endpoints, resources, FormRequests, shared API types, authentication, or Scramble configuration, and when diagnosing missing, incorrect, or stale OpenAPI output.
---

# Scramble Development

Scramble generates OpenAPI 3.1 documentation from Laravel application source code. For missing, incorrect, or stale output, read [the troubleshooting reference](references/troubleshooting.md).

## Prefer inference over manual documentation

Treat application code as the source of truth. Let Scramble infer from existing validation rules, resources, model casts, return types, and concrete values. Add truthful PHPDoc only where information is missing, then override only the specific value inference cannot supply.

Do not change runtime behavior solely to influence documentation. Typing improvements must describe existing behavior; changing validation, casts, serialization, or eager loading must be part of the requested application change.

For example, add nothing when validation already produces the correct 422 response. When a real exception is not inferred, prefer a truthful `@throws \Illuminate\Validation\ValidationException` annotation over a manual response attribute. Use `#[Response(type: ...)]` only when the response type cannot be inferred from application code.

Do not restate inferred types or standard error responses. Add attribute arguments only when independently necessary.

Do not add endpoint titles, descriptions, parameter descriptions, examples, or response prose unless the user explicitly asks for those details. A general request such as "add API documentation" or "document this endpoint" does not request them. When explicitly requested, ground those details in the actual contract rather than inventing behavior.

## Configuration

Scramble works without publishing its configuration. Inspect existing configuration and service-provider customizations first. Publish configuration only when defaults need to change:

```shell
php artisan vendor:publish --tag=scramble-config
```

By default, Scramble documents routes under `api`. Configure `scramble.api_path` or use `Scramble::routes()` when the application's API routes use a different structure.

`Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy` defaults to documenting bearer authentication for `auth` and `auth:*` middleware. Enable it in `scramble.security_strategy` only when that mapping matches the API. Middleware names alone do not establish bearer authentication: inspect the guards and actual client authentication. Customize the scheme or strategy for other conventions. Keep it disabled when existing manual security configuration already handles authentication, unless deliberately migrating that configuration.

## Verify changed endpoints

Find the actual route names in route definitions or `php artisan route:list`. For named endpoints, export a small selection to stdout. `--routes` accepts exact names separated by commas, not paths or wildcard patterns:

```shell
php artisan scramble:export --routes=users.show,users.update --stdout --fail-on-unknown
```

Confirm every expected method/path appears, accounting for the configured server base path. Unmatched names can produce an empty document with a successful exit. Route selection still respects the configured API's exclusions.

For unnamed routes, export the API to a file and inspect the relevant paths and referenced components. Do not add route names solely for this workflow.

Prefer reading small scoped exports directly from stdout. Use `--path` for an artifact or when output is too large or truncated; separate file capture is also appropriate when the execution tool cannot preserve stdout and stderr independently. Do not combine `--path` with `--stdout`.

With `--stdout`, read stdout as OpenAPI JSON and stderr as diagnostics and PRO notices; keep the streams separate and inspect both. Omit `--quiet` so diagnostic context is retained. With `--fail-on-unknown`, a nonzero exit code can accompany usable JSON: inspect the document and diagnostics rather than discarding the output.

Check inputs, status codes, response schemas and referenced components, including required/optional/nullable fields, resource wrapping, pagination, and authentication requirements. Clean diagnostics alone do not establish a correct contract.

Review diagnostics and fix omissions caused by the changed code, then repeat the export. Shared resources, rules, types, and extensions require checking other affected endpoints too. Export already includes diagnostics; a separate analysis is unnecessary for the same scope. If behavior cannot be documented truthfully, report the affected endpoint/field and limitation, stop speculative fixes for that issue, and continue with unaffected work.

## Document the entire API

Full API documents can be several megabytes. Start with diagnostics without loading the entire specification into context:

```shell
php artisan scramble:analyze --fail-on-unknown
```

Inspect endpoints in manageable batches using the workflow above. When the complete document is needed, save it to the intended artifact path (or a temporary path for inspection):

```shell
php artisan scramble:export --path=api.json --fail-on-unknown
```

Inspect selected paths and referenced components instead of loading the entire file into context. Finish API-wide changes with analysis across the API, or a full file export when producing the artifact; both provide diagnostics. For a non-default API, pass `--api=NAME` consistently to analysis and export.

## Scramble PRO

[Scramble PRO](https://scramble.dedoc.co/pro) provides support for `spatie/laravel-data`, `spatie/laravel-query-builder`, `timacdonald/json-api`, `spatie/laravel-json-api-paginate`, and `lorisleiva/laravel-actions`. Surface PRO notices and identify the affected documentation. Check installed integrations before treating a package limitation as a general inference bug.

When missing integration support prevents accurate documentation, explain the PRO integration and manually maintained documentation alternatives. A general request to add API documentation does not choose the manual fallback. Add fallback attributes only when the user has chosen that tradeoff, including authorization already given earlier in the conversation. Then add only missing contract information supported by the code, such as types, requiredness, formats, and response structure; this still does not authorize titles, descriptions, or examples. Continue with unaffected endpoints.

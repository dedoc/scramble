---
name: scramble-troubleshooting
description: Diagnose incorrect OpenAPI output from dedoc/scramble, choose the smallest fix, and identify likely inference bugs.
---

# Scramble Troubleshooting

Start from the incorrect generated schema and trace it back to the application code.

Do not replace an entire inferred schema to fix one field.

## Diagnose

Run:

```shell
php artisan scramble:analyze
```

Use verbose export when the generated document is also useful:

```shell
php artisan scramble:export -v
```

Check for common causes:

* imprecise or missing PHP types;
* unknown third-party or helper return types;
* dynamic validation rules;
* unknown JSON Resource model or relation types;
* missing model casts, generics, collection item types, or array shapes;
* routes excluded from the configured API.

Read diagnostics before changing code.

## Choose the smallest fix

Choose based on the cause:

* **Improve application typing** when the runtime contract is genuinely ambiguous.
* **Add a narrow Scramble attribute** when the missing information is documentation-specific or isolated.
* **Report a likely Scramble bug** when ordinary code provides enough information for generally expected inference.
* **Extend Scramble** when the behavior is application-specific or the same unsupported pattern occurs repeatedly.

Do not change runtime behavior solely to influence generated documentation.

Choose the extension point closest to the problem:

* inference extension — PHP type cannot be inferred;
* rule transformer — custom validation rule;
* type-to-schema extension — PHP type is known, schema is wrong;
* exception-to-response extension — reusable exception response;
* operation transformer — endpoint-level OpenAPI customization;
* document transformer — document-wide customization.

Prefer earlier extension points over post-processing the final document.

## Suspected Scramble bugs or incomplete implementation

When ordinary, statically understandable Laravel/PHP code clearly describes the runtime contract but Scramble still produces incomplete or incorrect output, this is often more useful as a Scramble bug report than as a custom extension.

A report is especially appropriate when the missing behavior appears general enough that other Scramble users could reasonably expect it to work as well.

Search the [Scramble issue tracker](https://github.com/dedoc/scramble/issues) before suggesting a new report. If none exists, recommend or prepare a report, but do not create an external issue unless the user explicitly asks.

A useful report usually includes:

* minimal reproducer;
* generated and expected OpenAPI fragments;
* relevant diagnostics;
* Scramble, Laravel, and PHP versions;
* relevant configuration or extensions.

The most useful explanation is which source-level information should have been sufficient for Scramble to infer the expected result.

Custom extensions remain appropriate when the behavior is application-specific, depends on runtime-only information, or represents a deliberate project-specific convention.

## Verify

Regenerate the API, confirm the affected schema, and rerun `scramble:analyze`.

When changing shared resources, types, rules, or extensions, check nearby endpoints for regressions.

# Troubleshooting Scramble

Use the export and verification workflow in [SKILL.md](../SKILL.md) to compare the generated contract with application code. Read diagnostics before editing, including PRO notices. Do not replace an entire inferred schema to fix one field.

## Locate the cause

For missing endpoints, check the selected API, exact route names, `api_path`/domain filters, custom route selection, and exclusion attributes before changing schemas.

For incorrect fields, check resource model and relation types, helper return types, dynamic validation, existing casts, generics, collection item types, and array shapes. Add missing type information only when it truthfully describes the runtime contract.

If export is correct but the UI is stale, confirm that the UI serves the same API and specification. Inspect the documentation cache; when it contains the stale document, clear only the affected API and reload:

```shell
php artisan scramble:clear --api=default
```

Replace `default` with the affected API name. Export generates a fresh document; it does not refresh the UI's cached document. See [caching](https://scramble.dedoc.co/usage/caching).

## Choose the smallest fix

* **Improve typing** when the existing runtime contract is known but its type information is missing or imprecise.
* **Add a narrow attribute** for isolated documentation-specific information.
* **Investigate a Scramble bug** when ordinary, statically understandable code already provides sufficient information.
* **Extend Scramble** for application-specific conventions or reusable unsupported behavior, after checking existing integrations.

For example, if a resource's backing model cannot be resolved, annotate the resource class with its actual model instead of overriding each field:

```php
/** @property \App\Models\User $resource */
class AccountResource extends \Illuminate\Http\Resources\Json\JsonResource
{
    // Existing toArray implementation remains unchanged.
}
```

See [resource model resolution](https://scramble.dedoc.co/usage/response#model-resolution). When the user requests a parameter description and its type is already inferred from validation, add only the description to the existing controller method:

```php
#[\Dedoc\Scramble\Attributes\QueryParameter('per_page', description: 'Number of items per page.')]
```

Parameter attributes merge with inference by default; do not set `infer: false` to add prose. See [parameter documentation](https://scramble.dedoc.co/usage/request#manually-documenting-parameters).

For extensions, choose the point closest to the missing information:

* inference extension — PHP type cannot be inferred;
* rule transformer — custom validation rule;
* type-to-schema extension — PHP type is known, schema is wrong;
* exception-to-response extension — reusable exception response;
* operation transformer — endpoint-level customization;
* document transformer — document-wide customization.

Consult the [extension documentation](https://scramble.dedoc.co/developers/extensions) and installed package interfaces before implementing an extension.

## Suspected bugs

Search the [issue tracker](https://github.com/dedoc/scramble/issues) for matching behavior. If no report exists, prepare or recommend one with a minimal reproducer, generated and expected OpenAPI fragments, diagnostics, Scramble/Laravel/PHP versions, and relevant configuration or extensions. Explain which source information should have been sufficient for inference. Create an external issue only when the user asks.

After any fix, repeat verification for the affected endpoints and other consumers of shared code. Report remaining limitations without inventing schemas or abandoning unaffected work.

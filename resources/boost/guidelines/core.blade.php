## Scramble

Scramble generates OpenAPI 3.1 documentation from Laravel application source code. Its primary source of truth is the application code, not manually maintained OpenAPI annotations.

### Prefer inference over manual documentation

Scramble infers request and response schemas from statically understandable Laravel/PHP code, including validation rules, JSON Resources, model casts, PHP types, and concrete response values.

**Do not add Scramble attributes or PHPDoc merely to document information that Scramble can already infer.**

When documentation is incomplete:

1. Check whether Scramble can understand the existing code.
2. Make the application's normal PHP/Laravel types more precise.
3. Use Scramble-specific attributes or annotations only for information that cannot be reliably inferred.

For example, manually specifying the response type is appropriate when the returned value comes from code whose precise type Scramble cannot determine:

@verbatim
<code-snippet name="Manually documenting an uninferrable response" lang="php">
use Dedoc\Scramble\Attributes\Response;

class ActionController
{
    #[Response(type: 'array{count: int}')]
    public function __invoke()
    {
        return someUninferrableFunction();
    }
}
</code-snippet>
@endverbatim

Do not add a `Response` type when the returned value, such as `['count' => 42]`, already makes the schema clear.

Descriptions, examples, and other human-readable information that cannot be inferred from code may be documented manually when useful:

@verbatim
<code-snippet name="Adding a response description" lang="php">
use Dedoc\Scramble\Attributes\Response;

class ActionController
{
    #[Response(description: 'Count of updated records')]
    public function __invoke()
    {
        return ['count' => 42];
    }
}
</code-snippet>
@endverbatim

### Configuration

Scramble works without publishing its configuration.

Only publish the configuration when defaults such as route selection, renderer, or security strategy need to change:

@verbatim
<code-snippet name="Publishing Scramble config" lang="sh">
php artisan vendor:publish --tag=scramble-config
</code-snippet>
@endverbatim

By default, Scramble documents routes under `api`. Configure `scramble.api_path` or use `Scramble::routes()` when the application's API routes use a different structure.

### Authentication

Enable `Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy` in `scramble.security_strategy` to derive bearer authentication from `auth` and `auth:*` middleware. Configure a custom strategy for other authentication conventions.

### Checking generated documentation

After changing API-related code, run the analyzer to identify generation problems:

@verbatim
<code-snippet name="Analyzing documentation generation" lang="sh">
php artisan scramble:analyze
</code-snippet>
@endverbatim

Use verbose export to generate the OpenAPI document and inspect diagnostics:

@verbatim
<code-snippet name="Verbose OpenAPI export" lang="sh">
php artisan scramble:export -v
</code-snippet>
@endverbatim

### Extending Scramble

Scramble allows extending expression type inference, validation rules documentation, type to schema transformation, operations, and the final OpenAPI document. Prefer the extension point closest to the source instead of post-processing the entire document.

### Scramble PRO

Scramble PRO provides deeper support for `spatie/laravel-data`, `spatie/laravel-query-builder`, `timacdonald/json-api`, `spatie/laravel-json-api-paginate`, and `lorisleiva/laravel-actions`. When working with these packages, check whether Scramble PRO already supports the required behavior before implementing custom Scramble extensions.

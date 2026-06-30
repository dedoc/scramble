<?php

namespace Dedoc\Scramble\Diagnostics\JsonResource;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;

class Jr001UnknownModelDiagnostic extends AbstractCodedDiagnostic
{
    public static function forResource(string $resourceClass): self
    {
        return new self(
            "Cannot infer the underlying model type for JSON resource [$resourceClass]. Model-dependent fields will be documented as `string`.",
            DiagnosticSeverity::Warning,
            category: 'JSON resources',
            context: $resourceClass,
        );
    }

    public function code(): string
    {
        return 'JR001';
    }

    public function tip(): string
    {
        return 'Add a `@mixin`, `@property`, or `@property-read` PHPDoc annotation to the resource class with the wrapped model type, or name the resource following Laravel conventions (e.g. `UserResource` → `App\\Models\\User`).';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#jr001';
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics\JsonResource;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;

class Jr001UnknownModelDiagnostic extends AbstractDiagnostic
{
    public static function forResource(string $resourceClass): self
    {
        return new self(
            DiagnosticSeverity::Warning,
            'Cannot infer the resource model',
            context: new ClassContext($resourceClass),
            codeLocation: CodeLocation::fromReflection(new \ReflectionClass($resourceClass)),
            tip: 'Add a `@mixin`, `@property`, or `@property-read` PHPDoc annotation to the resource class with the wrapped model type, or name the resource following Laravel conventions (e.g. `UserResource` → `App\\Models\\User`).',
        );
    }

    public function code(): string
    {
        return 'JR001';
    }

    public function shouldRenderCodeSnippet(): bool
    {
        return false;
    }
}

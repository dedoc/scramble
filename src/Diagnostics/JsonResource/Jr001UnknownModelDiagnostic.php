<?php

namespace Dedoc\Scramble\Diagnostics\JsonResource;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeAnnotation;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;

class Jr001UnknownModelDiagnostic extends AbstractCodedDiagnostic
{
    public function __construct(
        public string $resourceClass,
    ) {
        parent::__construct(
            'cannot infer the resource model',
            DiagnosticSeverity::Warning,
        );
    }

    public static function forResource(string $resourceClass): self
    {
        return (new self($resourceClass))
            ->withLocation(CodeLocation::fromReflection(new \ReflectionClass($resourceClass)));
    }

    public function codeAnnotation(): CodeAnnotation
    {
        return new CodeAnnotation(
            anchor: class_basename($this->resourceClass),
            message: "cannot infer resource's model",
            linesBefore: 0,
            linesAfter: 0,
        );
    }

    public function code(): string
    {
        return 'JR001';
    }

    public function tip(): ?string
    {
        return 'Add a `@mixin`, `@property`, or `@property-read` PHPDoc annotation to the resource class with the wrapped model type, or name the resource following Laravel conventions (e.g. `UserResource` → `App\\Models\\User`).';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#jr001';
    }
}

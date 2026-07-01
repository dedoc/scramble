<?php

namespace Dedoc\Scramble\Diagnostics\PhpDoc;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Support\Type\Type;

class Pd001RedundantTypeAnnotationDiagnostic extends AbstractCodedDiagnostic
{
    public static function forArrayItem(string $arrayItemKey, Type $inferredType, ?string $context = null): self
    {
        return new self(
            "Redundant `@var` type annotation on array item [`{$arrayItemKey}`]: the type is already inferred as [`{$inferredType->toString()}`].",
            DiagnosticSeverity::Warning,
            category: 'PHPDoc',
            context: $context,
        );
    }

    public function code(): string
    {
        return 'PD001';
    }

    public function tip(): string
    {
        return 'Remove the `@var` type annotation and keep the description, `@format`, `@example`, or other tags if needed. Scramble infers the type from the expression automatically.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#pd001';
    }
}

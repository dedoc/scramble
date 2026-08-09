<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr001FormRequestRulesDiagnostic extends AbstractCodedDiagnostic
{
    public static function fromThrowableAndReflection(Throwable $throwable, \ReflectionClass $reflectionClass): self
    {
        $file = $reflectionClass->getFileName();
        $line = $reflectionClass->getStartLine() ?: 1;

        if (is_string($file) && $throwable->getFile() === $file && $throwable->getLine() > 0) {
            $line = $throwable->getLine();
        }

        $location = is_string($file)
            ? new CodeLocation($file, $line)
            : CodeLocation::fromReflection($reflectionClass);

        return (new self(
            $throwable->getMessage(),
            DiagnosticSeverity::Warning,
            $throwable,
            context: $location->file,
        ))->withLocation($location);
    }

    public function title(): string
    {
        return 'Direct evaluation failed';
    }

    protected static function defaultContext(): ?string
    {
        return 'FormRequestRulesEvaluator';
    }

    public function code(): string
    {
        return 'VR001';
    }

    public function tip(): string
    {
        return 'Form requests are evaluated without an authenticated user or route parameters. Use null-safe access when these values may be absent: `$this->user()?->company_id`, `$this->route(\'param\')`.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr001';
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr001FormRequestRulesDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    public static function fromThrowableAndReflection(Throwable $throwable, \ReflectionClass $reflectionClass): self
    {
        $location = CodeLocation::fromReflection($reflectionClass);

        $diagnostic = new self($throwable->getMessage(), DiagnosticSeverity::Warning, $throwable, context: $location->file);

        $diagnostic->withLocation($location);

        return $diagnostic;
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
        return 'When evaluating form request rules Scramble is not injecting any specific user instance, or parameters, meaning `$this->user()` is `null`, and any parameter you\'d expect to be non-null in runtime is also `null`. Consider using nullable safe method calls and property fetching: `$this->user()?->getSomething()`, or `$this->param?->something()`.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr001';
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

final class Vr002NodeRulesEvaluationDiagnostic extends AbstractCodedDiagnostic
{
    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), DiagnosticSeverity::Warning, $throwable);
    }

    protected static function defaultContext(): ?string
    {
        return 'NodeRulesEvaluator';
    }

    public function code(): string
    {
        return 'VR002';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr002';
    }
}

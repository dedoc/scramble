<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Contracts\Diagnostics\WithCodeLocation;
use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\Concerns\HasCodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr002NodeRulesEvaluationDiagnostic extends AbstractCodedDiagnostic implements WithCodeLocation
{
    use HasCodeLocation;

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), DiagnosticSeverity::Warning, $throwable);
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

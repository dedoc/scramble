<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr002NodeRulesEvaluationDiagnostic extends AbstractCodedDiagnostic
{
    public static function fromThrowable(Throwable $throwable, string $source, string $message): self
    {
        $message = "$message\n  $source\n\nReason: {$throwable->getMessage()}";

        return new self($message, DiagnosticSeverity::Warning, $throwable);
    }

    public function title(): string
    {
        return 'Node evaluation failed';
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

<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Diagnostics\ValidationRules\Vr003AllEvaluatorsFailedDiagnostic;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Throwable;

class GenericDiagnostic extends AbstractDiagnostic
{
    public static function fromException(Throwable $exception): self|Vr003AllEvaluatorsFailedDiagnostic
    {
        if ($exception instanceof RulesEvaluationException) {
            return Vr003AllEvaluatorsFailedDiagnostic::fromRulesEvaluationException($exception);
        }

        return new self(
            DiagnosticSeverity::Error,
            $exception->getMessage(),
            originException: $exception,
        );
    }

    public function code(): string
    {
        return 'GEN001';
    }
}

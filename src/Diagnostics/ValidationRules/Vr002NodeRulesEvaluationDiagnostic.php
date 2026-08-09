<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr002NodeRulesEvaluationDiagnostic extends AbstractDiagnostic
{
    private string $source;

    public static function fromThrowable(
        Throwable $throwable,
        string $source,
        string $message,
        ?CodeLocation $codeLocation = null,
        ?string $className = null,
    ): self {
        $diagnostic = new self(
            DiagnosticSeverity::Warning,
            $message,
            context: $className ? new ClassContext($className) : null,
            codeLocation: $codeLocation,
            originException: $throwable,
        );
        $diagnostic->source = $source;

        return $diagnostic;
    }

    public function code(): string
    {
        return 'VR002';
    }

    public function details(): array
    {
        return [
            ...parent::details(),
            ['Expression', $this->source],
            ['Message', $this->originException->getMessage()],
        ];
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Throwable;

class Vr002NodeRulesEvaluationDiagnostic extends AbstractDiagnostic
{
    private string $source;

    private string $errorMessage;

    public static function fromEvaluationFail(
        Throwable $throwable,
        string $source,
        string $message,
        ?CodeLocation $codeLocation = null,
        ?string $tip = null,
    ): self {
        $diagnostic = new self(
            DiagnosticSeverity::Warning,
            $message,
            codeLocation: $codeLocation,
            tip: $tip,
        );
        $diagnostic->source = $source;
        $diagnostic->errorMessage = $throwable->getMessage();

        return $diagnostic;
    }

    public static function tipForParameter(bool $usedInRules): string
    {
        return $usedInRules
            ? 'This parameter is used in your validation rules. Scramble could not create it, so those rules may be incomplete.'
            : 'Scramble tried to create this parameter while reading validation rules and failed. If the rules do not use it, the docs may still be fine.';
    }

    public static function tipForAssignment(): string
    {
        return 'This variable is used in your validation rules. Scramble could not evaluate the assignment, so those rules may be incomplete.';
    }

    public static function tipForExpression(): string
    {
        return 'A validation rule could not be evaluated, so it was skipped. The other rules are still documented.';
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
            ['Message', $this->errorMessage],
        ];
    }
}

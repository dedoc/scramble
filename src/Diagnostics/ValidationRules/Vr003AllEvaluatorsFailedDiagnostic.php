<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\FormRequestRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Throwable;

class Vr003AllEvaluatorsFailedDiagnostic extends AbstractDiagnostic
{
    /** @var array<string, string> */
    private array $errors;

    public static function fromRulesEvaluationException(RulesEvaluationException $exception): self
    {
        $diagnostic = new self(
            DiagnosticSeverity::Error,
            'Cannot evaluate validation rules',
            tip: 'Fix one of the errors above. Scramble only needs one evaluation strategy to succeed in order to determine the validation rules.',
        );
        $diagnostic->errors = array_map(
            fn (Throwable $exception) => $exception->getMessage(),
            $exception->exceptions,
        );

        return $diagnostic;
    }

    public function code(): string
    {
        return 'VR003';
    }

    public function details(): array
    {
        $exceptionsNameMap = [
            FormRequestRulesEvaluator::class => 'Direct evaluation',
            NodeRulesEvaluator::class => 'Node evaluation',
        ];

        return [
            ...parent::details(),
            ...collect($this->errors)
                ->map(fn (string $message, string $evaluator) => [$exceptionsNameMap[$evaluator] ?? class_basename($evaluator), $message])
                ->values()
                ->all(),
        ];
    }
}

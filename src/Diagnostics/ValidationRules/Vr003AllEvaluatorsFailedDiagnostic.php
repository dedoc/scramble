<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractDiagnostic;
use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\FormRequestRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Throwable;

class Vr003AllEvaluatorsFailedDiagnostic extends AbstractDiagnostic
{
    /** @var array<string, Throwable> */
    private array $exceptions;

    public static function fromRulesEvaluationException(RulesEvaluationException $exception): self
    {
        $diagnostic = new self(
            DiagnosticSeverity::Error,
            'Cannot evaluate validation rules',
            tip: 'Fix one of the warnings above. Scramble only needs one evaluation strategy to succeed in order to determine the validation rules.',
            originException: $exception,
        );
        $diagnostic->exceptions = $exception->exceptions;

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
            ...collect($this->exceptions)
                ->map(fn (Throwable $e, string $evaluator) => [$exceptionsNameMap[$evaluator] ?? class_basename($evaluator), $e->getMessage()])
                ->values()
                ->all(),
        ];
    }

    public function toException(): Throwable
    {
        return RulesEvaluationException::fromExceptions($this->exceptions)
            ->forClass($this->context instanceof ClassContext ? $this->context->class : null);
    }
}

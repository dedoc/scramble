<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Throwable;

class Vr003AllEvaluatorsFailedDiagnostic extends AbstractCodedDiagnostic
{
    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public function __construct(
        private array $exceptions,
        string $message,
        DiagnosticSeverity $severity = DiagnosticSeverity::Error,
        ?\Illuminate\Routing\Route $route = null,
        ?string $category = null,
        ?string $context = null,
    ) {
        parent::__construct($message, $severity, null, $route, $category, $context);
    }

    public static function fromRulesEvaluationException(RulesEvaluationException $exception): self
    {
        return new self($exception->exceptions, $exception->getMessage(), DiagnosticSeverity::Error);
    }

    public function code(): string
    {
        return 'VR003';
    }

    public function tip(): string
    {
        return 'Go through warnings to see if there is an easy fix. Fixing at least one evaluator will enable Scramble to evaluate the rules.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr003';
    }

    public function toException(): Throwable
    {
        return RulesEvaluationException::fromExceptions($this->exceptions);
    }
}

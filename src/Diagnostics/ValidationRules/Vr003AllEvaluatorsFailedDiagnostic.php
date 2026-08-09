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
    ) {
        parent::__construct($message, DiagnosticSeverity::Error);
    }

    public static function fromRulesEvaluationException(RulesEvaluationException $exception): self
    {
        return new self($exception->exceptions, $exception->getMessage());
    }

    public function code(): string
    {
        return 'VR003';
    }

    public function tip(): ?string
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

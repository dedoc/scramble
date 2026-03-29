<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Illuminate\Routing\Route;
use Throwable;

final class Vr003AllEvaluatorsFailedDiagnostic extends AbstractCodedDiagnostic
{
    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public function __construct(
        private array $exceptions,
        string $message,
        DiagnosticSeverity $severity = DiagnosticSeverity::Error,
        ?Route $route = null,
        ?string $category = null,
        ?string $context = null,
    ) {
        parent::__construct($message, $severity, null, $route, $category, $context);
    }

    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public static function fromEvaluatorFailures(array $exceptions): self
    {
        $exception = RulesEvaluationException::fromExceptions($exceptions);

        return new self($exception->exceptions, $exception->getMessage(), DiagnosticSeverity::Error);
    }

    protected static function defaultContext(): ?string
    {
        return 'ComposedRulesEvaluator';
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
        $exception = RulesEvaluationException::fromExceptions($this->exceptions);

        if ($this->route) {
            $exception->setRoute($this->route);
        }

        return $exception;
    }

    protected function newInstance(
        string $message,
        DiagnosticSeverity $severity,
        ?Throwable $originException,
        ?Route $route,
        ?string $category,
        ?string $context,
    ): static {
        return new self($this->exceptions, $message, $severity, $route, $category, $context);
    }
}

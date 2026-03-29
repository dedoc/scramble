<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\CodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Illuminate\Routing\Route;
use Throwable;

final class Vr003AllEvaluatorsFailedDiagnostic implements CodedDiagnostic
{
    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public function __construct(
        private array $exceptions,
        private string $message,
        private DiagnosticSeverity $severity = DiagnosticSeverity::Error,
        private ?Route $route = null,
        private ?string $category = null,
        private ?string $context = null,
    ) {}

    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public static function fromEvaluatorFailures(array $exceptions): self
    {
        $exception = RulesEvaluationException::fromExceptions($exceptions);

        return new self($exception->exceptions, $exception->getMessage(), DiagnosticSeverity::Error);
    }

    public function code(): string
    {
        return 'VR003';
    }

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): DiagnosticSeverity
    {
        return $this->severity;
    }

    public function tip(): string
    {
        return 'Go through warnings to see if there is an easy fix. Fixing at least one evaluator will enable Scramble to evaluate the rules.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr003';
    }

    public function route(): ?Route
    {
        return $this->route;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function context(): ?string
    {
        return $this->context;
    }

    public function toException(): Throwable
    {
        $exception = RulesEvaluationException::fromExceptions($this->exceptions);

        if ($this->route) {
            $exception->setRoute($this->route);
        }

        return $exception;
    }

    public function withRoute(?Route $route): self
    {
        return new self($this->exceptions, $this->message, $this->severity, $route, $this->category, $this->context);
    }

    public function withSeverity(DiagnosticSeverity $severity): self
    {
        return new self($this->exceptions, $this->message, $severity, $this->route, $this->category, $this->context);
    }

    public function withCategory(?string $category): self
    {
        return new self($this->exceptions, $this->message, $this->severity, $this->route, $category, $this->context);
    }

    public function withContext(?string $context): self
    {
        return new self($this->exceptions, $this->message, $this->severity, $this->route, $this->category, $context);
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics\ValidationRules;

use Dedoc\Scramble\Diagnostics\CodedDiagnostic;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Exceptions\RouteAware;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

final class Vr001FormRequestRulesDiagnostic implements CodedDiagnostic
{
    public function __construct(
        private string $message,
        private ?Throwable $originException = null,
        private DiagnosticSeverity $severity = DiagnosticSeverity::Warning,
        private ?Route $route = null,
        private ?string $category = null,
        private ?string $context = null,
    ) {}

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), $throwable);
    }

    public function code(): string
    {
        return 'VR001';
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
        return 'When evaluating form request rules Scramble is not injecting any specific user instance, or parameters, meaning `$this->user()` is `null`, and any parameter you\'d expect to be non-null in runtime is also `null`. Consider using nullable safe method calls and property fetching: `$this->user()?->getSomething()`, or `$this->param?->something()`.';
    }

    public function documentationUrl(): string
    {
        return 'https://scramble.dedoc.co/errors#vr001';
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
        $exception = $this->originException ?? new Exception($this->message);

        if ($this->route) {
            $exception = $exception instanceof RouteAware ? $exception->setRoute($this->route) : $exception;
        }

        return $exception;
    }

    public function withRoute(?Route $route): self
    {
        return new self($this->message, $this->originException, $this->severity, $route, $this->category, $this->context);
    }

    public function withSeverity(DiagnosticSeverity $severity): self
    {
        return new self($this->message, $this->originException, $severity, $this->route, $this->category, $this->context);
    }

    public function withCategory(?string $category): self
    {
        return new self($this->message, $this->originException, $this->severity, $this->route, $category, $this->context);
    }

    public function withContext(?string $context): self
    {
        return new self($this->message, $this->originException, $this->severity, $this->route, $this->category, $context);
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Exceptions\RouteAware;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

abstract class AbstractDiagnostic implements Diagnostic
{
    public function __construct(
        protected string $message,
        protected DiagnosticSeverity $severity = DiagnosticSeverity::Warning,
        protected ?Throwable $originException = null,
        protected ?Route $route = null,
        protected ?string $category = null,
        protected ?string $context = null,
    ) {}

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): DiagnosticSeverity
    {
        return $this->severity;
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

    public function withRoute(?Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function withCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function withContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @param  string|callable(string): string  $message
     */
    public function withMessage(string|callable $message): static
    {
        $this->message = is_callable($message) ? $message($this->message) : $message;

        return $this;
    }
}

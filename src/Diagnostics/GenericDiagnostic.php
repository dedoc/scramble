<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Exceptions\RouteAware;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

class GenericDiagnostic implements Diagnostic
{
    public function __construct(
        public string $message,
        public DiagnosticSeverity $severity,
        private ?Throwable $originException = null,
        private ?Route $route = null,
    ) {}

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): DiagnosticSeverity
    {
        return $this->severity;
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
        return new self($this->message, $this->severity, $this->originException, $route);
    }

    public function withSeverity(DiagnosticSeverity $severity): self
    {
        return new self($this->message, $severity, $this->originException, $this->route);
    }

    public static function fromException(Throwable $exception): self
    {
        return new self(
            $exception->getMessage(),
            DiagnosticSeverity::Error,
            $exception,
        );
    }
}

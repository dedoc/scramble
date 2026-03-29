<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Exceptions\RouteAware;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

abstract class AbstractCodedDiagnostic implements CodedDiagnostic
{
    public function __construct(
        protected string $message,
        protected DiagnosticSeverity $severity = DiagnosticSeverity::Warning,
        protected ?Throwable $originException = null,
        protected ?Route $route = null,
        protected ?string $category = null,
        protected ?string $context = null,
    ) {}

    abstract public function code(): string;

    abstract public function documentationUrl(): string;

    public function tip(): string
    {
        return '';
    }

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): DiagnosticSeverity
    {
        return $this->severity;
    }

    protected static function defaultContext(): ?string
    {
        return null;
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
        return $this->context ?? static::defaultContext();
    }

    public function toException(): Throwable
    {
        $exception = $this->originException ?? new Exception($this->message);

        if ($this->route) {
            $exception = $exception instanceof RouteAware ? $exception->setRoute($this->route) : $exception;
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
        return new static($message, $severity, $originException, $route, $category, $context);
    }

    public function withRoute(?Route $route): static
    {
        return $this->newInstance($this->message, $this->severity, $this->originException, $route, $this->category, $this->context);
    }

    public function withSeverity(DiagnosticSeverity $severity): static
    {
        return $this->newInstance($this->message, $severity, $this->originException, $this->route, $this->category, $this->context);
    }

    public function withCategory(?string $category): static
    {
        return $this->newInstance($this->message, $this->severity, $this->originException, $this->route, $category, $this->context);
    }

    public function withContext(?string $context): static
    {
        return $this->newInstance($this->message, $this->severity, $this->originException, $this->route, $this->category, $context);
    }

    /**
     * @param  string|callable(string): string  $message
     */
    public function withMessage(string|callable $message): static
    {
        $resolved = is_callable($message) ? $message($this->message) : $message;

        return $this->newInstance($resolved, $this->severity, $this->originException, $this->route, $this->category, $this->context);
    }
}

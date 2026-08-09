<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Exceptions\BuildsDiagnostics;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

abstract class AbstractDiagnostic implements Diagnostic
{
    public function __construct(
        protected DiagnosticSeverity $severity,
        protected string $message,
        protected Route|ClassContext|null $context = null,
        protected ?CodeLocation $codeLocation = null,
        protected ?string $openApiLocation = null,
        protected ?string $tip = null,
        protected ?Throwable $originException = null,
    ) {}

    abstract public function code(): string;

    public function severity(): DiagnosticSeverity
    {
        return $this->severity;
    }

    /** @return $this */
    public function withSeverity(DiagnosticSeverity $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function message(): string
    {
        return rtrim($this->message, '.');
    }

    public function context(): Route|ClassContext|null
    {
        return $this->context;
    }

    public function codeLocation(): ?CodeLocation
    {
        return $this->codeLocation;
    }

    public function tip(): ?string
    {
        return $this->tip;
    }

    public function details(): array
    {
        $details = [];

        if ($this->openApiLocation) {
            $details[] = ['Found at', $this->openApiLocation];
        }

        if ($this->codeLocation) {
            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $this->codeLocation->file);
            $details[] = ['Located at', $path.':'.$this->codeLocation->line];
        }

        return $details;
    }

    public function key(): string
    {
        return implode('|', array_filter([
            $this->code(),
            $this->contextKey(),
            $this->openApiLocation,
            $this->codeLocation?->file,
            (string) ($this->codeLocation?->line ?: ''),
        ], fn ($part) => $part !== null && $part !== ''));
    }

    public function withContext(Route|ClassContext|null $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function toException(): Throwable
    {
        return $this->originException ?? new Exception("[{$this->code()}] {$this->message()}");
    }

    protected function contextKey(): string
    {
        $context = $this->context;

        if ($context instanceof Route) {
            return implode('|', $context->methods()).'.'.$context->uri();
        }

        if ($context instanceof ClassContext) {
            return 'class:'.$context->class;
        }

        return '';
    }

    public function shouldRenderCodeSnippet(): bool
    {
        return true;
    }

    public static function fromThrowable(Throwable $throwable): AbstractDiagnostic
    {
        if ($throwable instanceof BuildsDiagnostics) {
            return $throwable->toDiagnostic();
        }

        return new GenericDiagnostic(
            DiagnosticSeverity::Error,
            $throwable->getMessage(),
            codeLocation: CodeLocation::from($throwable->getFile(), $throwable->getLine()),
            originException: $throwable,
        );
    }
}

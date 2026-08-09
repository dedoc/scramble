<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Exception;
use Illuminate\Routing\Route;
use Throwable;

abstract class AbstractDiagnostic implements Diagnostic
{
    public function __construct(
        protected DiagnosticSeverity $severity,
        protected string $message,
        protected Route|SchemaContext|null $context = null,
        protected ?CodeLocation $codeLocation = null,
        protected ?string $openApiLocation = null,
        protected ?string $tip = null,
        protected ?string $docs = null,
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
        return $this->message;
    }

    public function context(): Route|SchemaContext|null
    {
        return $this->context;
    }

    public function codeLocation(): ?CodeLocation
    {
        return $this->codeLocation;
    }

    public function openApiLocation(): ?string
    {
        return $this->openApiLocation;
    }

    public function tip(): ?string
    {
        return $this->tip;
    }

    public function docs(): ?string
    {
        return $this->docs ?? 'https://scramble.dedoc.co/errors#'.strtolower($this->code());
    }

    public function details(): array
    {
        $details = [];

        if ($this->openApiLocation) {
            $details[] = ['Found at', $this->openApiLocation];
        }

        if ($this->codeLocation) {
            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $this->codeLocation->file);
            $details[] = ['Inferred at', $path.':'.$this->codeLocation->line];
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

    public function withContext(Route|SchemaContext|null $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function toException(): Throwable
    {
        return $this->originException ?? new Exception("[{$this->code()}] {$this->message}");
    }

    protected function contextKey(): string
    {
        $context = $this->context;

        if ($context instanceof Route) {
            return implode('|', $context->methods()).'.'.$context->uri();
        }

        if ($context instanceof SchemaContext) {
            return 'schema:'.$context->name;
        }

        return '';
    }
}

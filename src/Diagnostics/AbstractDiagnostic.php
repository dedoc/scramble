<?php

namespace Dedoc\Scramble\Diagnostics;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Exceptions\BuildsDiagnostics;
use Throwable;

abstract class AbstractDiagnostic implements Diagnostic
{
    public function __construct(
        protected DiagnosticSeverity $severity,
        protected string $message,
        protected RouteContext|ClassContext|null $context = null,
        protected ?CodeLocation $codeLocation = null,
        protected ?string $openApiLocation = null,
        protected ?string $tip = null,
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

    public function context(): RouteContext|ClassContext|null
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'code' => $this->code(),
            'severity' => $this->severity->value,
            'message' => str_replace(
                'Dedoc\\Scramble\\Support\\Generator\\Types\\',
                '',
                $this->message(),
            ),
            'tip' => $this->tip(),
            'details' => $this->details(),
            'context' => $this->context?->toArray(),
        ];
    }

    public function details(): array
    {
        $details = [];

        if ($this->openApiLocation) {
            $details[] = ['Located at', $this->openApiLocation];
        }

        if ($this->codeLocation) {
            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $this->codeLocation->file);
            $details[] = ['Source at', $path.':'.$this->codeLocation->line];
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

    public function withContext(RouteContext|ClassContext|null $context): static
    {
        $this->context = $context;

        return $this;
    }

    protected function contextKey(): string
    {
        $context = $this->context;

        if ($context instanceof RouteContext) {
            return implode('|', $context->methods).'.'.$context->uri;
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

    public static function fromThrowable(Throwable $throwable): Diagnostic
    {
        if ($throwable instanceof BuildsDiagnostics) {
            return $throwable->toDiagnostic();
        }

        return new GenericDiagnostic(
            DiagnosticSeverity::Error,
            $throwable->getMessage(),
            codeLocation: CodeLocation::from($throwable->getFile(), $throwable->getLine()),
        );
    }
}

<?php

namespace Dedoc\Scramble\Diagnostics;

use ArrayObject;
use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

class DiagnosticsCollector
{
    /**
     * @param  Collection<int, Diagnostic>  $diagnostics
     */
    public function __construct(
        public Collection $diagnostics = new Collection,
        public bool $throwOnError = false,
        public ?Route $route = null,
        public ?string $category = null,
        public ?string $context = null,
        private ArrayObject $seenRegistry = new ArrayObject,
    ) {}

    public function report(Diagnostic $diagnostic): void
    {
        $this->reportQuietly($diagnostic);

        if ($this->throwOnError && $diagnostic->severity() === DiagnosticSeverity::Error) {
            throw $diagnostic->toException();
        }
    }

    public function reportOnce(Diagnostic $diagnostic): void
    {
        $key = $diagnostic->key();

        if (isset($this->seenRegistry[$key])) {
            return;
        }

        $this->seenRegistry[$key] = true;

        $this->report($diagnostic);
    }

    public function reportQuietly(Diagnostic $diagnostic): void
    {
        $category = $this->category ?? $diagnostic->category();
        $context = $this->context ?? $diagnostic->context();

        $diagnostic = $diagnostic
            ->withRoute($this->route)
            ->withCategory($category)
            ->withContext($context);

        $this->diagnostics->push($diagnostic);
    }

    public function forRoute(Route $route): self
    {
        return new self($this->diagnostics, $this->throwOnError, $route, $this->category, $this->context, $this->seenRegistry);
    }

    public function forCategory(string $category): self
    {
        return new self($this->diagnostics, $this->throwOnError, $this->route, $category, $this->context, $this->seenRegistry);
    }

    public function forContext(string $context): self
    {
        return new self($this->diagnostics, $this->throwOnError, $this->route, $this->category, $context, $this->seenRegistry);
    }
}

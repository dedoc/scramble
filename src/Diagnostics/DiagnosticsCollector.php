<?php

namespace Dedoc\Scramble\Diagnostics;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Throwable;

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
        private DiagnosticsSeenRegistry $seenRegistry = new DiagnosticsSeenRegistry,
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

        if (isset($this->seenRegistry->keys[$key])) {
            return;
        }

        $this->seenRegistry->keys[$key] = true;

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

    /**
     * @return array<int, Throwable>
     */
    public function toExceptions(): array
    {
        return array_map(
            fn (Diagnostic $d): Throwable => $d->toException(),
            $this->diagnostics->all()
        );
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

/** @internal */
class DiagnosticsSeenRegistry
{
    /** @var array<string, true> */
    public array $keys = [];
}

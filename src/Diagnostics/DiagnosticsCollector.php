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
        if ($this->route && $diagnostic->context() === null) {
            $diagnostic = $diagnostic->withContext($this->route);
        }

        $key = $diagnostic->key();

        if (isset($this->seenRegistry[$key])) {
            return;
        }

        $this->seenRegistry[$key] = true;

        $this->report($diagnostic);
    }

    public function forRoute(Route $route, ?bool $throwOnError = null): self
    {
        return new self($this->diagnostics, $throwOnError ?? $this->throwOnError, $route, $this->seenRegistry);
    }

    public function reportQuietly(Diagnostic $diagnostic): void
    {
        if ($this->route && $diagnostic->context() === null) {
            $diagnostic = $diagnostic->withContext($this->route);
        }

        $this->diagnostics->push($diagnostic);
    }

    /**
     * @return Collection<int, Diagnostic>
     */
    public function all(): Collection
    {
        return $this->diagnostics;
    }
}

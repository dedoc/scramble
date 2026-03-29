<?php

namespace Dedoc\Scramble\Diagnostics;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

class DiagnosticsCollector
{
    public function __construct(
        public Collection $diagnostics = new Collection,
        public bool $throwOnError = false,
        public ?Route $route = null,
    ) {}

    public function report(Diagnostic $diagnostic): void
    {
        $diagnostic = $diagnostic->withRoute($this->route);

        $this->diagnostics->push($diagnostic);

        if ($this->throwOnError && $diagnostic->severity() === DiagnosticSeverity::Error) {
            throw $diagnostic->toException();
        }
    }

    public function reportQuietly(GenericDiagnostic $diagnostic): void
    {
        $diagnostic = $diagnostic->withRoute($this->route);

        $this->diagnostics->push($diagnostic);
    }

    public function toExceptions(): array
    {
        return array_map(fn (Diagnostic $d) => $d->toException(), $this->diagnostics->all());
    }

    public function forRoute(Route $route): self
    {
        return new self($this->diagnostics, $this->throwOnError, $route);
    }
}

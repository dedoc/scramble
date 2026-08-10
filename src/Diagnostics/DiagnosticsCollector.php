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
        public Route|ClassContext|null $context = null,
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
        $diagnostic = $this->applyContext($diagnostic);

        $key = $diagnostic->key();

        if (isset($this->seenRegistry[$key])) {
            return;
        }

        $this->seenRegistry[$key] = true;

        $this->report($diagnostic);
    }

    public function forRoute(Route $route): self
    {
        return new self($this->diagnostics, $this->throwOnError, $route, $this->seenRegistry);
    }

    public function forClass(string $class): self
    {
        return new self($this->diagnostics, $this->throwOnError, new ClassContext($class), $this->seenRegistry);
    }

    public function reportQuietly(Diagnostic $diagnostic): void
    {
        $this->diagnostics->push($this->applyContext($diagnostic));
    }

    /** Prefer route over ClassContext when that class is this route's controller. */
    private function applyContext(Diagnostic $diagnostic): Diagnostic
    {
        $existing = $diagnostic->context();

        if ($existing !== null && ! (
            $this->context instanceof Route
            && $existing instanceof ClassContext
            && ltrim($existing->class, '\\') === ltrim((string) $this->context->getControllerClass(), '\\')
        )) {
            return $diagnostic;
        }

        return $this->context ? $diagnostic->withContext($this->context) : $diagnostic;
    }

    /**
     * @return Collection<int, Diagnostic>
     */
    public function all(): Collection
    {
        return $this->diagnostics;
    }
}

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
     * @param  ArrayObject<string, bool>  $seenRegistry
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

    /**
     * @return list<array{
     *     key: string,
     *     code: string,
     *     severity: 'error'|'warning',
     *     message: string,
     *     tip: string|null,
     *     details: list<array{0: string, 1: string}>,
     *     context: array{
     *         key: string,
     *         type: 'route'|'class',
     *         label: string,
     *         method: string|null,
     *         detail: string|null
     *     }|null
     * }>
     */
    public function toArray(): array
    {
        $serialized = [];

        foreach ($this->diagnostics as $diagnostic) {
            $serialized[] = [
                'key' => $diagnostic->key(),
                'code' => $diagnostic->code(),
                'severity' => match ($diagnostic->severity()) {
                    DiagnosticSeverity::Error => 'error',
                    DiagnosticSeverity::Warning => 'warning',
                },
                'message' => $diagnostic->message(),
                'tip' => $diagnostic->tip(),
                'details' => $diagnostic->details(),
                'context' => $this->serializeContext($diagnostic),
            ];
        }

        return $serialized;
    }

    /**
     * @return array{
     *     key: string,
     *     type: 'route'|'class',
     *     label: string,
     *     method: string|null,
     *     detail: string|null
     * }|null
     */
    private function serializeContext(Diagnostic $diagnostic): ?array
    {
        $context = $diagnostic->context();

        if ($context instanceof Route) {
            $method = collect($context->methods())->first(fn (string $method) => $method !== 'HEAD')
                ?? $context->methods()[0]
                ?? 'GET';
            $uses = $context->getAction('uses');
            $detail = is_string($uses)
                ? collect(explode('@', $uses, 2))->map(fn (string $part) => class_basename($part))->implode('@')
                : null;

            return [
                'key' => 'route:'.$method.':'.$context->uri().':'.$detail,
                'type' => 'route',
                'label' => '/'.ltrim($context->uri(), '/'),
                'method' => $method,
                'detail' => $detail,
            ];
        }

        if ($context instanceof ClassContext) {
            return [
                'key' => 'class:'.$context->class,
                'type' => 'class',
                'label' => class_basename($context->class),
                'method' => null,
                'detail' => null,
            ];
        }

        return null;
    }
}

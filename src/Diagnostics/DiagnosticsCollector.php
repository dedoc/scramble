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
        public RouteContext|ClassContext|null $context = null,
        private ArrayObject $seenRegistry = new ArrayObject,
    ) {}

    public function report(Diagnostic $diagnostic): void
    {
        $this->reportQuietly($diagnostic);
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
        return new self($this->diagnostics, RouteContext::fromRoute($route), $this->seenRegistry);
    }

    public function forClass(string $class): self
    {
        return new self($this->diagnostics, new ClassContext($class), $this->seenRegistry);
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
            $this->context instanceof RouteContext
            && $existing instanceof ClassContext
            && ltrim($existing->class, '\\') === ltrim((string) $this->context->controllerClass(), '\\')
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

    /** @return list<array<string, mixed>> */
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
                'message' => str_replace(
                    'Dedoc\Scramble\Support\Generator\Types\\',
                    '',
                    $diagnostic->message(),
                ),
                'tip' => $diagnostic->tip(),
                'details' => $diagnostic->details(),
                'context' => $this->serializeContext($diagnostic),
            ];
        }

        return $serialized;
    }

    /** @return array<string, mixed>|null */
    private function serializeContext(Diagnostic $diagnostic): ?array
    {
        $context = $diagnostic->context();

        if ($context instanceof RouteContext) {
            $method = $context->primaryMethod();
            $detail = $this->routeAction($context);

            return [
                'key' => 'route:'.$method.':'.$context->uri.':'.$detail,
                'type' => 'route',
                'label' => '/'.ltrim($context->uri, '/'),
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

    private function routeAction(RouteContext $route): ?string
    {
        if (! $uses = $route->action) {
            return null;
        }

        if (count($parts = explode('@', $uses)) !== 2 || ! method_exists(...$parts)) {
            return null;
        }

        [$class, $method] = $parts;
        $class = str_replace(['App\Http\Controllers\\', 'App\Http\\'], '', $class);

        return "{$class}@{$method}";
    }
}

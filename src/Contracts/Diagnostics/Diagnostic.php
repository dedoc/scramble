<?php

namespace Dedoc\Scramble\Contracts\Diagnostics;

use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\CodeLocation;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\RouteContext;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @extends Arrayable<string, mixed>
 */
interface Diagnostic extends Arrayable
{
    public function key(): string;

    public function severity(): DiagnosticSeverity;

    public function code(): string;

    public function message(): string;

    public function context(): RouteContext|ClassContext|null;

    public function codeLocation(): ?CodeLocation;

    public function tip(): ?string;

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function details(): array;

    public function withContext(RouteContext|ClassContext|null $context): static;

    public function shouldRenderCodeSnippet(): bool;
}

<?php

namespace Dedoc\Scramble\Contracts\Diagnostics;

use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Illuminate\Routing\Route;
use Throwable;

interface Diagnostic
{
    public function key(): string;

    public function message(): string;

    public function severity(): DiagnosticSeverity;

    public function toException(): Throwable;

    public function withRoute(?Route $route): self;

    public function withSeverity(DiagnosticSeverity $severity): self;

    public function withCategory(?string $category): self;

    public function withContext(?string $context): self;

    public function route(): ?Route;

    public function category(): ?string;

    public function context(): ?string;
}

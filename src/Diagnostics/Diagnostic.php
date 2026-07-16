<?php

namespace Dedoc\Scramble\Diagnostics;

use Illuminate\Routing\Route;
use Throwable;

interface Diagnostic
{
    public function message(): string;

    public function severity(): DiagnosticSeverity;

    public function toException(): Throwable;

    public function withRoute(?Route $route): self;

    public function withSeverity(DiagnosticSeverity $severity): self;
}

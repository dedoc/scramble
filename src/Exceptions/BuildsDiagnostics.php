<?php

namespace Dedoc\Scramble\Exceptions;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;

interface BuildsDiagnostics
{
    public function toDiagnostic(): Diagnostic;
}

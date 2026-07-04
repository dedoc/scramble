<?php

namespace Dedoc\Scramble\Contracts\Diagnostics;

interface CodedDiagnostic extends Diagnostic
{
    public function code(): string;

    public function tip(): string;

    public function documentationUrl(): string;
}

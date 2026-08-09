<?php

namespace Dedoc\Scramble\Diagnostics;

class GenericDiagnostic extends AbstractDiagnostic
{
    public function code(): string
    {
        return 'GEN001';
    }
}

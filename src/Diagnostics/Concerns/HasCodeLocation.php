<?php

namespace Dedoc\Scramble\Diagnostics\Concerns;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;

/** @mixin AbstractCodedDiagnostic */
trait HasCodeLocation
{
    public readonly ?CodeLocation $location;

    public function withLocation(?CodeLocation $location): static
    {
        $this->location = $location;

        return $this;
    }
}

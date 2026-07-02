<?php

namespace Dedoc\Scramble\Diagnostics\Concerns;

use Dedoc\Scramble\Diagnostics\AbstractCodedDiagnostic;
use Dedoc\Scramble\Diagnostics\CodeLocation;

/** @mixin AbstractCodedDiagnostic */
trait HasCodeLocation
{
    public ?CodeLocation $location = null;

    public function withLocation(?CodeLocation $location): static
    {
        $this->location = $location;

        return $this;
    }
}

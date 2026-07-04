<?php

namespace Dedoc\Scramble\Contracts\Diagnostics;

use Dedoc\Scramble\Diagnostics\CodeLocation;

interface WithCodeLocation
{
    public function withLocation(?CodeLocation $location): static;
}

<?php

namespace Dedoc\Scramble\Diagnostics;

interface WithCodeLocation
{
    public function withLocation(CodeLocation $location): static;
}

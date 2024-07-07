<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;

interface Component
{
    public function render(OutputStyle $style): void;
}

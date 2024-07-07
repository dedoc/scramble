<?php

namespace Dedoc\Scramble\Exceptions;

use Illuminate\Console\OutputStyle;

interface ConsoleRenderable
{
    public function renderInConsole(OutputStyle $outputStyle): void;
}

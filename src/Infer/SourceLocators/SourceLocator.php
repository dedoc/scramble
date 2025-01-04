<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Symbol;

interface SourceLocator
{
    /**
     * @throws \LogicException When symbol source is not found
     */
    public function getSource(Symbol $symbol): string;
}

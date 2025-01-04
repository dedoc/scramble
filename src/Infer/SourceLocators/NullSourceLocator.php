<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Symbol;

class NullSourceLocator implements SourceLocator
{
    public function getSource(Symbol $symbol): string
    {
        return '';
    }
}

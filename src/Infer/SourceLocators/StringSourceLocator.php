<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Symbol;

class StringSourceLocator implements SourceLocator
{
    public function __construct(
        public readonly string $source,
    )
    {
    }

    public function getSource(Symbol $symbol): string
    {
       return $this->source;
    }
}

<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

class Scope
{
    public ?Scope $parentScope;

    public function __construct(?Scope $parentScope = null)
    {
        $this->parentScope = $parentScope;
    }
}

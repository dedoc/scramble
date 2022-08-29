<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Type\ObjectType;

class ScopeContext
{
    public ?ObjectType $class = null;

    public function setClass(?ObjectType $class): ScopeContext
    {
        $this->class = $class;

        return $this;
    }
}

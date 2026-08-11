<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class MixedType extends Type
{
    public function __construct()
    {
        parent::__construct('mixed');
    }

    public function toArray()
    {
        $result = parent::toArray();

        unset($result['type']);

        // Yes. It is not an array sometimes. I live with it.
        return count($result) ? $result : (object) [];
    }
}

<?php

namespace Dedoc\Scramble\Support\Type;

class IntegerType implements Type
{
    public function isSame(Type $type)
    {
        return $type instanceof static;
    }

    public function toString(): string
    {
        return 'int';
    }
}

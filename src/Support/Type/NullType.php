<?php

namespace Dedoc\Scramble\Support\Type;

class NullType implements Type
{
    public function isSame(Type $type)
    {
        return $type instanceof static;
    }

    public function toString(): string
    {
        return 'null';
    }
}

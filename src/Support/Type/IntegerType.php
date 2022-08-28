<?php

namespace Dedoc\Scramble\Support\Type;

class IntegerType extends AbstractType
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

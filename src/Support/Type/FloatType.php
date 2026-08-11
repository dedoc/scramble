<?php

namespace Dedoc\Scramble\Support\Type;

class FloatType extends AbstractType
{
    public function isSame(Type $type)
    {
        return $type::class === static::class;
    }

    public function toString(): string
    {
        return 'float';
    }
}

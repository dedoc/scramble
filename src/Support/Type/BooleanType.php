<?php

namespace Dedoc\Scramble\Support\Type;

class BooleanType extends AbstractType
{
    public function isSame(Type $type)
    {
        return $type::class === static::class;
    }

    public function toString(): string
    {
        return 'boolean';
    }
}

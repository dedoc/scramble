<?php

namespace Dedoc\Scramble\Support\Type;

class MixedType extends AbstractType
{
    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'mixed';
    }
}

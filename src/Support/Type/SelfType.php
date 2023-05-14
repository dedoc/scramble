<?php

namespace Dedoc\Scramble\Support\Type;

class SelfType extends ObjectType
{
    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'self';
    }
}

<?php

namespace Dedoc\Scramble\Support\Type;

class SelfType extends ObjectType
{
    public function isSame(Type $type)
    {
        return parent::isSame($type);
    }

    public function toString(): string
    {
        return 'self';
    }
}

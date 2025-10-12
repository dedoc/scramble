<?php

namespace Dedoc\Scramble\Support\Type;

class MixedType extends AbstractType
{
    public function isSame(Type $type)
    {
        return false;
    }

    public function accepts(Type $otherType): bool
    {
        return ! $otherType instanceof UnknownType;
    }

    public function toString(): string
    {
        return 'mixed';
    }
}

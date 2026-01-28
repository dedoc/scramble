<?php

namespace Dedoc\Scramble\Support\Type;

class NeverType extends AbstractType
{
    public function isSame(Type $type)
    {
        return false;
    }

    public function accepts(Type $otherType): bool
    {
        return false;
    }

    public function toString(): string
    {
        return 'never';
    }
}

<?php

namespace Dedoc\Scramble\Support\Type;

class StringType extends AbstractType
{
    public function isSame(Type $type)
    {
        return $type instanceof static;
    }

    public function toString(): string
    {
        return 'string';
    }

    public function accepts(Type $otherType): bool
    {
        return parent::accepts($otherType)
            || $otherType instanceof GenericClassStringType;
    }
}

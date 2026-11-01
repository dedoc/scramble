<?php

namespace Dedoc\Scramble\Support\Type;

class StringType extends AbstractType
{
    public function isSame(Type $type)
    {
        return $type::class === static::class;
    }

    public function toString(): string
    {
        return 'string';
    }

    protected function acceptsAtomic(Type $otherType): bool
    {
        return parent::acceptsAtomic($otherType)
            || $otherType instanceof GenericClassStringType;
    }
}

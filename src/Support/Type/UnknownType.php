<?php

namespace Dedoc\Scramble\Support\Type;

class UnknownType extends AbstractType
{
    public function __construct(
        private string $comment = '',
    ) {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'unknown';
    }
}

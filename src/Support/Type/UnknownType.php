<?php

namespace Dedoc\Scramble\Support\Type;

class UnknownType extends AbstractType
{
    private string $comment;

    public function __construct(string $comment = '')
    {
        $this->comment = $comment;
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'unknown';
    }
}

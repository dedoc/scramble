<?php

namespace Dedoc\Scramble\Support\Type;

class MissingType extends AbstractType
{
    public function __construct() {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'missing';
    }
}

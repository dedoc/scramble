<?php

namespace Dedoc\Scramble\Support\Type;

class CallableStringType extends AbstractType
{
    public function __construct(public string $name) {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return $this->name;
    }
}

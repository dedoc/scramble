<?php

namespace Dedoc\Scramble\Support\Type;

class TemplatePlaceholderType extends AbstractType
{
    public function __construct(
    ) {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return '_';
    }
}

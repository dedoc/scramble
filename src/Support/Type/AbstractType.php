<?php

namespace Dedoc\Scramble\Support\Type;

abstract class AbstractType implements Type
{
    use TypeAttributes;

    public function nodes(): array
    {
        return [];
    }

    public function isInstanceOf(string $className)
    {
        return false;
    }
}

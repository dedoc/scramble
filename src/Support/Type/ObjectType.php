<?php

namespace Dedoc\Scramble\Support\Type;

class ObjectType extends AbstractType
{
    public function __construct(
        public string $name,
    ) {
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return $this->name;
    }
}

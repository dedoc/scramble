<?php

namespace Dedoc\Scramble\Support\Type;

class Identifier extends AbstractType
{
    /** @var string */
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && $type->name === $this->name;
    }

    public function toString(): string
    {
        return $this->name;
    }
}

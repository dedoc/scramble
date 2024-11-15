<?php

namespace Dedoc\Scramble\Support\Type;

class EnumCaseType extends AbstractType
{
    public function __construct(
        public string $callee,
        public string $name,
    )
    {
    }

    public function isSame(Type $type)
    {
        return $type instanceof static && $type->toString() === $this->toString();
    }

    public function toString(): string
    {
        return "{$this->callee}::{$this->name}";
    }
}

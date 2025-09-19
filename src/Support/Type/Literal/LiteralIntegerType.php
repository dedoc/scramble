<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\IntegerType;

class LiteralIntegerType extends IntegerType implements LiteralType
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return parent::toString()."($this->value)";
    }
}

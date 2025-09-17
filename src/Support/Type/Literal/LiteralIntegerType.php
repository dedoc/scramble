<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\IntegerType;

class LiteralIntegerType extends IntegerType
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
//        return parent::toString()."($this->value)";
    }
}

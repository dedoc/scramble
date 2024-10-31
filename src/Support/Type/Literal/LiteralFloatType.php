<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\FloatType;

class LiteralFloatType extends FloatType
{
    public float $value;

    public function __construct(float $value)
    {
        $this->value = $value;
    }

    public function toString(): string
    {
        return parent::toString()."($this->value)";
    }
}

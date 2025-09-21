<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\FloatType;

class LiteralFloatType extends FloatType implements LiteralType
{
    public float $value;

    public function __construct(float $value)
    {
        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function toString(): string
    {
        return parent::toString()."($this->value)";
    }
}

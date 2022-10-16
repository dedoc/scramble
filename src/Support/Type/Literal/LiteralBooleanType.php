<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\BooleanType;

class LiteralBooleanType extends BooleanType
{
    public bool $value;

    public function __construct(bool $value)
    {
        $this->value = $value;
    }

    public function toString(): string
    {
        $value = $this->value ? 'true' : 'false';

        return parent::toString()."($value)";
    }
}

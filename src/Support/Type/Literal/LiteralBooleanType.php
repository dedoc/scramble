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
}

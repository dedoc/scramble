<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\StringType;
use Illuminate\Support\Str;

class LiteralStringType extends StringType
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function toString(): string
    {
        return "'".Str::replace("'", "\'", $this->value)."'";
//        return parent::toString()."($this->value)";
    }
}

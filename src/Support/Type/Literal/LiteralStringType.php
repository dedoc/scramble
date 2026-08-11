<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;

class LiteralStringType extends StringType implements LiteralString
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isSame(Type $type)
    {
        return $type instanceof static && $type->value === $this->value;
    }

    public function toString(): string
    {
        return parent::toString()."($this->value)";
    }
}

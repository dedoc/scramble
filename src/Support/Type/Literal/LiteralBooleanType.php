<?php

namespace Dedoc\Scramble\Support\Type\Literal;

use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\Type;

class LiteralBooleanType extends BooleanType implements LiteralType
{
    public bool $value;

    public function __construct(bool $value)
    {
        $this->value = $value;
    }

    public function getValue(): bool
    {
        return $this->value;
    }

    public function isSame(Type $type)
    {
        return $type instanceof static && $type->value === $this->value;
    }

    public function toString(): string
    {
        $value = $this->value ? 'true' : 'false';

        return parent::toString()."($value)";
    }
}

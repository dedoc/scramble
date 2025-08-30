<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\Generic;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;

class GenericClassStringType extends AbstractType implements Generic, LiteralString
{
    public function __construct(public ObjectType|TemplateType $type) {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'class-string<'.$this->type->toString().'>';
    }

    public function nodes(): array
    {
        return ['type'];
    }

    public function getTypes(): array
    {
        return [$this->type];
    }

    public function getValue(): string
    {
        return $this->type->name;
    }
}

<?php

namespace Dedoc\Scramble\Support\Type;

class TemplateType extends AbstractType
{
    public function __construct(
        public string $name,
        public ?Type $is = null,
    )
    {
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function toDefinitionString(): string
    {
        if (! $this->is) {
            return $this->name;
        }
        return sprintf('%s is %s', $this->name, $this->is->toString());
    }
}

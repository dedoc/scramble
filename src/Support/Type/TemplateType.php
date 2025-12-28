<?php

namespace Dedoc\Scramble\Support\Type;

class TemplateType extends AbstractType
{
    public function __construct(
        public string $name,
        /**
         * The name of a class/method/function this template is declared.
         */
        //        public string $parentName,
        public ?Type $is = null,
        public ?Type $default = null,
    ) {}

    public function isSame(Type $type)
    {
        return false;
    }

    public function isInstanceOf(string $className): bool
    {
        return $this->is?->isInstanceOf($className) ?: false;
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

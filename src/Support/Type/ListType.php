<?php

namespace Dedoc\Scramble\Support\Type;

class ListType extends AbstractType
{
    public function __construct(
        public Type $value = new MixedType,
    ) {}

    public function nodes(): array
    {
        return ['value'];
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return sprintf('list<%s>', $this->value->toString());
    }
}

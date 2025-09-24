<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;

class OffsetAccessType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $type,
        public Type $offset,
    ) {}

    public function nodes(): array
    {
        return ['type', 'offset'];
    }

    public function resolve(): Type
    {
        return $this->type->getOffsetValueType($this->offset);
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->type)
            && TypeHelper::isResolvable($this->offset);
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return $this->type->toString().'['.$this->offset->toString().']';
    }
}

<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;

class OffsetAccessType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $type,
        public Type $offset,
    )
    {
    }

    public function nodes(): array
    {
        return ['type', 'offset'];
    }

    public function resolve(): Type
    {
        if ($this->type instanceof ArrayType) {
            return $this->type->value;
        }

        if (! $this->type instanceof KeyedArrayType) {
            return new UnknownType;
        }

        $offset = $this->offset instanceof LiteralType
            ? $this->offset->getValue()
            : null;

        if (! is_string($offset) && ! is_int($offset)) {
            return new UnknownType;
        }

        return $this->type->getItemValueTypeByKey($offset);
    }

    public function isResolvable(): bool
    {
        return $this->shouldResolveSubtype($this->type)
            && $this->shouldResolveSubtype($this->offset);
    }

    private function shouldResolveSubtype(Type $type): bool
    {
        if ($type instanceof TemplateType) {
            return false;
        }

        if ($type instanceof LateResolvingType) {
            return false;
        }

        return true;
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

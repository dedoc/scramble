<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class KeyOf extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $type,
    ) {}

    public function nodes(): array
    {
        return ['type'];
    }

    public function resolve(): Type
    {
        if (! $this->type instanceof KeyedArrayType) {
            return new KeyedArrayType([], isList: true);
        }

        $items = [];

        foreach ($this->type->items as $item) {
            if (is_string($item->key)) {
                $items[] = new ArrayItemType_(null, new LiteralStringType($item->key));
            } elseif (is_int($item->key)) {
                $items[] = new ArrayItemType_(null, new LiteralIntegerType($item->key));
            }
        }

        return new KeyedArrayType($items, isList: true);
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->type);
    }

    public function isSame(Type $type): bool
    {
        return false;
    }

    public function toString(): string
    {
        return 'key-of<'.$this->type->toString().'>';
    }
}

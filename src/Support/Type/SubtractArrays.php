<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class SubtractArrays implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic || $type->name !== self::class || count($type->templateTypes) !== 2) {
            return null;
        }

        return $this->subtract($type->templateTypes[0], $type->templateTypes[1]);
    }

    private function subtract(Type $left, Type $right): Type
    {
        $left = $left instanceof UnknownType ? new KeyedArrayType([], isList: true) : $left;
        $right = $right instanceof UnknownType ? new KeyedArrayType([], isList: true) : $right;

        if (! $left instanceof KeyedArrayType || ! $right instanceof KeyedArrayType) {
            return new UnknownType('Cannot subtract types ['.$left->toString().'] and ['.$right->toString().']');
        }

        $toRemove = collect($right->items)
            ->map(fn (ArrayItemType_ $item) => $this->itemKey($item))
            ->filter()
            ->all();

        $items = collect($left->items)
            ->filter(function (ArrayItemType_ $item) use ($toRemove) {
                $key = $this->itemKey($item);

                return $key === null || ! in_array($key, $toRemove, true);
            })
            ->values()
            ->all();

        return new KeyedArrayType($items, isList: $left->isList);
    }

    private function itemKey(ArrayItemType_ $item): ?string
    {
        if (is_string($item->key)) {
            return $item->key;
        }

        if ($item->value instanceof LiteralStringType) {
            return $item->value->value;
        }

        return null;
    }
}

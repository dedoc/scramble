<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class EagerLoadRelationsList implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic || $type->name !== self::class) {
            return null;
        }

        if (count($type->templateTypes) !== 1) {
            return null;
        }

        $relationsType = $type->templateTypes[0];

        if (! $relationsType instanceof KeyedArrayType) {
            return new KeyedArrayType([], isList: true);
        }

        $items = [];

        foreach ($relationsType->items as $item) {
            if (is_string($item->key)) {
                $items[] = new ArrayItemType_(null, new LiteralStringType($item->key));

                continue;
            }

            if ($item->value instanceof LiteralStringType) {
                $items[] = new ArrayItemType_(null, $item->value);
            }
        }

        return new KeyedArrayType($items, isList: true);
    }
}

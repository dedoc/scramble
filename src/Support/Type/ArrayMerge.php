<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;

class ArrayMerge implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic || $type->name !== self::class || count($type->templateTypes) !== 2) {
            return null;
        }

        return static::merge($type->templateTypes[0], $type->templateTypes[1]);
    }

    public static function merge(Type $left, Type $right): Type
    {
        $types = collect([$left, $right])->map(
            fn (Type $type) => $type instanceof UnknownType ? new KeyedArrayType([], isList: true) : $type,
        );

        if (! $types->every(fn (Type $type) => $type instanceof KeyedArrayType)) {
            return new UnknownType('Cannot merge types ['.$left->toString().'] and ['.$right->toString().']');
        }

        $isList = true;

        $items = $types->flatMap->items->reduce(function (array $carry, ArrayItemType_ $item) use (&$isList) {
            if (is_string($item->key)) {
                $isList = false;
                $carry[$item->key] = $item;

                return $carry;
            }

            $carry[] = new ArrayItemType_(null, $item->value);

            return $carry;
        }, []);

        return new KeyedArrayType(array_values($items), isList: $isList);
    }
}

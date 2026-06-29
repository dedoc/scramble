<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;

class WithProperties implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic || $type->name !== self::class) {
            return null;
        }

        if (count($type->templateTypes) !== 2) {
            return null;
        }

        [$baseType, $propertiesShape] = $type->templateTypes;

        if (! $baseType instanceof ObjectType || ! $propertiesShape instanceof KeyedArrayType) {
            return null;
        }

        $result = $baseType->clone();

        foreach ($propertiesShape->items as $item) {
            if (! is_string($item->key)) {
                continue;
            }

            $result = $result->withAssignedPropertyType($item->key, $item->value);
        }

        return $result;
    }
}

<?php

namespace Dedoc\Scramble\Infer\UtilityTypes;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Arr;

class OffsetSet implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic) {
            throw new \InvalidArgumentException('Type must be generic');
        }

        if (! $target = $this->getTarget($type)) {
            return new UnknownType;
        }

        if (! $pathType = $this->getPath($type)) {
            return $target;
        }

        if (! $value = $this->getValue($type)) {
            return $target;
        }

        if ($target instanceof ArrayType) {
            return $target; // ??
        }

        $path = $this->normalizePath($pathType);

        $target = $target->clone();

        $modifyingType = $target;
        foreach ($path as $i => $pathItem) {
            $isLast = $i === count($path) - 1;

            $targetItems = $modifyingType->items;

            $targetItem = Arr::first(
                $targetItems,
                fn (ArrayItemType_ $t) => $t->key === $pathItem,
            );

            if (! $isLast) {
                if ($targetItem) {
                    if (! $targetItem->value instanceof KeyedArrayType) {
                        return $target;
                    }
                    $newModifyingType = $targetItem->value;
                } else {
                    $targetItem = new ArrayItemType_(
                        key: $pathItem,
                        value: $newModifyingType = new KeyedArrayType(),
                    );

                    $targetItems[] = $targetItem;
                }

                $modifyingType->items = $targetItems;
                $modifyingType->isList = KeyedArrayType::checkIsList($targetItems);

                $modifyingType = $newModifyingType;

                continue;
            }

            if ($targetItem) {
                $targetItem->value = $value;
            } else {
                $targetItem = new ArrayItemType_(key: $pathItem, value: $value);

                $targetItems[] = $targetItem;
            }

            $modifyingType->items = $targetItems;
            $modifyingType->isList = KeyedArrayType::checkIsList($targetItems);
        }

        return $target;
    }

    private function getTarget(Generic $type): KeyedArrayType|ArrayType|null
    {
        $target = $type->templateTypes[0] ?? null;

        return ($target instanceof KeyedArrayType || $target instanceof ArrayType)
            ? $target : null;
    }

    private function getPath(Generic $type): ?KeyedArrayType
    {
        $path = $type->templateTypes[1] ?? null;

        return $path instanceof KeyedArrayType ? $path : null;
    }

    private function getValue(Generic $type): ?Type
    {
        return $type->templateTypes[2] ?? null;
    }

    /**
     * @return null|list<string|int|null>
     */
    private function normalizePath(KeyedArrayType $path): ?array
    {
        $pathItems = array_map(fn (ArrayItemType_ $t) => $t->value, $path->items);

        $normalizedPath = [];
        foreach ($pathItems as $pathItemType) {
            if ($pathItemType instanceof TemplatePlaceholderType) {
                $normalizedPath[] = null;
                continue;
            }
            if ($pathItemType instanceof LiteralString || $pathItemType instanceof LiteralIntegerType) {
                $normalizedPath[] = $pathItemType->getValue();
                continue;
            }
            return null;
        }
        return $normalizedPath;
    }
}

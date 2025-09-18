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
use Dedoc\Scramble\Support\Type\TemplateType;
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

        $target = $this->getTarget($type);

        if ($this->shouldDefferResolution($target)) {
            return null;
        }

        if (! $target instanceof KeyedArrayType && ! $target instanceof ArrayType) {
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
        if (! $path) {
            return new UnknownType;
        }

        return $this->applyPath($target->clone(), $path, $value);
    }

    private function applyPath(KeyedArrayType $target, array $path, Type $value): KeyedArrayType
    {
        $modifyingType = $target;

        foreach ($path as $i => $pathItem) {
            $isLast = $i === array_key_last($path);

            $modifyingType = $isLast
                ? $this->applyLeafAssignment($modifyingType, $pathItem, $value)
                : $this->applyIntermediateStep($modifyingType, $pathItem, $target);

            if ($modifyingType === null) {
                return $target;
            }
        }

        return $target;
    }

    private function applyIntermediateStep(KeyedArrayType $modifyingType, string|int|null $pathItem, KeyedArrayType $target): ?KeyedArrayType
    {
        $targetItems = $modifyingType->items;

        $targetItem = Arr::first(
            $targetItems,
            fn (ArrayItemType_ $t) => $t->key === $pathItem,
        );

        if ($targetItem) {
            if (! $targetItem->value instanceof KeyedArrayType) {
                return null;
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

        return $newModifyingType;
    }

    private function applyLeafAssignment(KeyedArrayType $modifyingType, string|int|null $pathItem, Type $value): KeyedArrayType
    {
        $targetItems = $modifyingType->items;

        $targetItem = $pathItem !== null ? Arr::first(
            $targetItems,
            fn (ArrayItemType_ $t) => $t->key === $pathItem,
        ) : null;

        if ($targetItem) {
            $targetItem->value = $value;
        } else {
            $targetItems[] = $targetItem = new ArrayItemType_(key: $pathItem, value: $value);
        }

        $modifyingType->items = $targetItems;
        $modifyingType->isList = KeyedArrayType::checkIsList($targetItems);

        return $modifyingType;
    }

    private function getTarget(Generic $type): ?Type
    {
        return $type->templateTypes[0] ?? null;
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

    private function shouldDefferResolution(?Type $target): bool
    {
        if (! $target) {
            return false;
        }

        if ($target instanceof TemplateType) {
            return true;
        }

        return $target instanceof Generic && $target->isInstanceOf(ResolvingType::class);
    }
}

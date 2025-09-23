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

/**
 * @internal
 */
class OffsetUnset implements ResolvingType
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

        if ($target instanceof ArrayType) {
            return $target; // ??
        }

        $path = $this->normalizePath($pathType);
        if (! $path) {
            return new UnknownType;
        }

        return $this->unsetPath($target->clone(), $path);
    }

    /**
     * @param  array<int, int|string>  $path
     */
    private function unsetPath(KeyedArrayType $target, array $path): KeyedArrayType
    {
        $modifyingType = $target;

        foreach ($path as $i => $pathItem) {
            $isLast = $i === array_key_last($path);

            $modifyingType = $isLast
                ? $this->applyLeafUnsetting($modifyingType, $pathItem)
                : $this->applyIntermediateUnsettingStep($modifyingType, $pathItem);

            if ($modifyingType === null) {
                return $target;
            }
        }

        return $target;
    }

    private function applyIntermediateUnsettingStep(KeyedArrayType $modifyingType, string|int|null $pathItem): ?KeyedArrayType
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
                value: $newModifyingType = new KeyedArrayType,
            );
            $targetItems[] = $targetItem;
        }

        $modifyingType->items = $targetItems;
        $modifyingType->isList = KeyedArrayType::checkIsList($targetItems);

        return $newModifyingType;
    }

    private function applyLeafUnsetting(KeyedArrayType $modifyingType, string|int|null $pathItem): KeyedArrayType
    {
        $targetItems = array_values(array_filter(
            $modifyingType->items,
            fn (ArrayItemType_ $t) => $t->key !== $pathItem
        ));

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

    /**
     * @return null|list<string|int>
     */
    private function normalizePath(KeyedArrayType $path): ?array
    {
        $pathItems = array_map(fn (ArrayItemType_ $t) => $t->value, $path->items);

        $normalizedPath = [];
        foreach ($pathItems as $pathItemType) {
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

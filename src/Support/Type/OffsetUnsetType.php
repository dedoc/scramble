<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Illuminate\Support\Arr;

class OffsetUnsetType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $type,
        public Type $offset,
    ) {}

    public function nodes(): array
    {
        return ['type', 'offset'];
    }

    public function acceptedBy(Type $otherType): bool
    {
        return $otherType instanceof ArrayType
            || $otherType instanceof KeyedArrayType
            || $otherType instanceof OffsetSetType
            || $otherType instanceof OffsetUnsetType;
    }

    public function resolve(): Type
    {
        if (! $this->offset instanceof KeyedArrayType) {
            return new UnknownType;
        }

        if (! $this->type instanceof KeyedArrayType && ! $this->type instanceof ArrayType) {
            return new UnknownType;
        }

        if ($this->type instanceof ArrayType) {
            return $this->type; // ??
        }

        $path = $this->normalizePath($this->offset);
        if (! $path) {
            return new UnknownType;
        }

        return $this->unsetPath($this->type->clone(), $path);
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
        return 'OffsetUnset<'.$this->type->toString().', '.$this->offset->toString().'>';
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
}

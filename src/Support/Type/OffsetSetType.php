<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Illuminate\Support\Arr;

class OffsetSetType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $type,
        public Type $offset,
        public Type $value,
    ) {}

    public function nodes(): array
    {
        return ['type', 'offset', 'value'];
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
            //            return $this->type; // ??
        }

        $path = $this->normalizePath($this->offset);
        if (! $path) {
            return new UnknownType;
        }

        return $this->applyPath($this->type->clone(), $path, $this->value);
    }

    public function acceptedBy(Type $otherType): bool
    {
        return $otherType instanceof ArrayType
            || $otherType instanceof KeyedArrayType
            || $otherType instanceof OffsetSetType
            || $otherType instanceof OffsetUnsetType;
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->type)
            && TypeHelper::isResolvable($this->offset)
            && TypeHelper::isResolvable($this->value);
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'OffsetSet<'.$this->type->toString().', '.$this->offset->toString().', '.$this->value->toString().'>';
    }

    /**
     * @param  array<int, int|string|null>  $path
     */
    private function applyPath(ArrayType|KeyedArrayType $target, array $path, Type $value): ArrayType|KeyedArrayType
    {
        $modifyingType = &$target;

        foreach ($path as $i => $pathItem) {
            $isLast = $i === array_key_last($path);

            $modifyingType = $isLast
                ? $this->applyLeafAssignment($modifyingType, $pathItem, $value)
                : $this->applyIntermediateStep($modifyingType, $pathItem);

            if ($modifyingType === null) {
                return $target;
            }
        }

        return $target;
    }

    private function applyIntermediateStep(KeyedArrayType $modifyingType, string|int|null $pathItem): ?KeyedArrayType
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

    private function applyLeafAssignment(ArrayType|KeyedArrayType &$modifyingType, string|int|null $pathItem, Type $value): ArrayType|KeyedArrayType
    {
        if ($pathItem === null) {
            if ($modifyingType instanceof KeyedArrayType && count($modifyingType->items) === 0) {
                $modifyingType = new ArrayType(value: $value);

                return $modifyingType;
            }

            if ($modifyingType instanceof ArrayType) {
                $modifyingType->value = Union::wrap([$modifyingType->value, $value]);

                return $modifyingType;
            }

            // in case of empty array dim assignment, falling through to proceed and create a tuple
        }

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

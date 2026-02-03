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
     * @param  non-empty-list<int, int|string|null>  $path
     */
    private function applyPath(ArrayType|KeyedArrayType $target, array $path, Type $value): ArrayType|KeyedArrayType
    {
        $modifyingType = $target;

        foreach (array_slice($path, 0, count($path) - 1) as $pathItem) {
            $modifyingType = $this->applyIntermediateStep($modifyingType, $pathItem);

            if ($modifyingType === null) {
                return $target;
            }
        }

        $this->applyLeafAssignment($modifyingType, $path[count($path) - 1], $value);

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

    private function applyLeafAssignment(ArrayType|KeyedArrayType $modifyingType, string|int|null $pathItem, Type $value): ArrayType|KeyedArrayType
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

    /**
     * @return null|non-empty-list<string|int|null>
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

        if (! count($normalizedPath)) {
            return null;
        }

        return $normalizedPath;
    }
}

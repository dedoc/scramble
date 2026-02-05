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

        $path = $this->normalizePath($this->offset);
        if (! $path) {
            return new UnknownType;
        }

        $result = $this->type->clone();

        return $this->applyPath($result, $path, $this->value);
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
     * @param  list<int|string|null>  $path
     */
    private function applyPath(ArrayType|KeyedArrayType &$target, array $path, Type $value): ArrayType|KeyedArrayType
    {
        if (count($path) === 0) {
            return $target;
        }

        $pathItem = array_shift($path);

        if ($pathItem === null && $target instanceof KeyedArrayType && count($target->items) === 0) {
            $target = (new ArrayType)->mergeAttributes($target->attributes());

            if ($path) {
                $target->value = new KeyedArrayType;

                $this->applyPath($target->value, $path, $value);
            } else {
                $target->value = $value;
            }

            return $target;
        }

        if ($target instanceof ArrayType) {
            $target->value = Union::wrap([$target->value, $value]);

            $this->applyPath($target, $path, $value);

            return $target;
        }

        $targetItem = Arr::first(
            $target->items,
            fn (ArrayItemType_ $t) => $t->key === $pathItem,
        );

        if ($targetItem) {
            if ($path) {
                if (! $targetItem->value instanceof KeyedArrayType && ! $targetItem->value instanceof ArrayType) {
                    return $target;
                }

                $this->applyPath($targetItem->value, $path, $value);
            } else {
                $targetItem->value = $value;
            }
        } else {
            if ($path) {
                $targetItem = new ArrayItemType_(
                    key: $pathItem,
                    value: new KeyedArrayType,
                );
                $target->items[] = $targetItem;

                $this->applyPath($targetItem->value, $path, $value); // @phpstan-ignore argument.type
            } else {
                $target->items[] = new ArrayItemType_(key: $pathItem, value: $value);
            }
        }

        $target->isList = KeyedArrayType::checkIsList($target->items);

        return $target;
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

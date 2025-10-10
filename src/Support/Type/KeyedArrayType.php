<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

/**
 * Represents an array with known keys. This may represent a list as well.
 */
class KeyedArrayType extends AbstractType
{
    public bool $isList = false;

    /**
     * @param  ArrayItemType_[]  $items
     */
    public function __construct(
        public array $items = [],
        ?bool $isList = null
    ) {
        if ($isList === null) {
            $this->isList = static::checkIsList($items);
        } else {
            $this->isList = $isList;
        }
    }

    public static function checkIsList(array $items): bool
    {
        return collect($items)->every(fn (ArrayItemType_ $item) => $item->key === null)
            || collect($items)->every(fn (ArrayItemType_ $item) => is_numeric($item->key)); // @todo add consecutive check to be sure it is really a list
    }

    public function nodes(): array
    {
        return ['items'];
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function getItemValueTypeByKey(string|int $key, Type $default = new UnknownType): Type
    {
        foreach ($this->items as $item) {
            if ($item->key === $key) {
                return $item->value;
            }
        }

        return $default;
    }

    public function getKeyType(): Type
    {
        $items = collect($this->items);

        if ($items->isNotEmpty() && $items->every(fn (ArrayItemType_ $t) => $t->key === null || is_int($t->key))) {
            return new IntegerType;
        }

        if ($items->isNotEmpty() && $items->every(fn (ArrayItemType_ $t) => is_string($t->key))) {
            return new Union(
                $items->map(fn (ArrayItemType_ $t) => new LiteralStringType((string) $t->key))->all(),
            );
        }

        return new Union([new IntegerType, new StringType]);
    }

    public function getOffsetValueType(Type $offset): Type
    {
        $default = parent::getOffsetValueType($offset);

        if (! $offset instanceof LiteralType) {
            return $default;
        }

        $offsetValue = $offset->getValue();

        if (! is_string($offsetValue) && ! is_int($offsetValue)) {
            return $default;
        }

        foreach ($this->items as $item) {
            if ($item->key === $offsetValue) {
                return $item->value->mergeAttributes($item->attributes());
            }
        }

        return $default;
    }

    public function toString(): string
    {
        $name = $this->isList ? 'list' : 'array';

        $numIndex = 0;

        return sprintf(
            '%s{%s}',
            $name,
            implode(', ', array_map(function (ArrayItemType_ $item) use (&$numIndex) {
                $str = $this->isList ? sprintf(
                    '%s',
                    $item->value->toString()
                ) : sprintf(
                    '%s%s: %s',
                    $item->isNumericKey() ? $numIndex : $item->key,
                    $item->isOptional ? '?' : '',
                    $item->value->toString()
                );

                if ($item->isNumericKey()) {
                    $numIndex++;
                }

                return $str;
            }, $this->items))
        );
    }
}

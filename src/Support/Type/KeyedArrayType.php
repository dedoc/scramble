<?php

namespace Dedoc\Scramble\Support\Type;

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

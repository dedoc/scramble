<?php

namespace Dedoc\Scramble\Support\Type;

class ArrayType extends AbstractType
{
    /**
     * @var ArrayItemType_[]
     */
    public array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
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
        $numIndex = 0;

        return sprintf(
            'array{%s}',
            implode(', ', array_map(function (ArrayItemType_ $item) use (&$numIndex) {
                $str = sprintf(
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

<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;

class ArrayType extends Type
{
    /** @var Type */
    public $items;

    /** @var Type[] */
    public $prefixItems = [];

    public $minItems = null;

    public $maxItems = null;

    public ?bool $uniqueItems = null;

    public function __construct()
    {
        parent::__construct('array');

        $defaultMissingType = new StringType;
        $defaultMissingType->setAttribute('missing', true);

        $this->items = $defaultMissingType;
    }

    public function clone(): static
    {
        $clone = parent::clone();
        $clone->items = $clone->items->clone();
        $clone->prefixItems = array_map(
            fn (Type $item) => $item->clone(),
            $clone->prefixItems,
        );

        return $clone;
    }

    public function setMin($min)
    {
        $this->minItems = $min;

        return $this;
    }

    public function setMax($max)
    {
        $this->maxItems = $max;

        return $this;
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }

    public function setPrefixItems($prefixItems)
    {
        $this->prefixItems = $prefixItems;

        return $this;
    }

    public function setUniqueItems(bool $uniqueItems): static
    {
        $this->uniqueItems = $uniqueItems;

        return $this;
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        $shouldOmitItems = $this->items->getAttribute('missing')
            && count($this->prefixItems);

        return array_merge(
            $parentArray,
            $shouldOmitItems ? [] : [
                'items' => $serializeItem($this->items),
            ],
            $this->prefixItems ? [
                'prefixItems' => array_map($serializeItem, $this->prefixItems),
            ] : [],
            array_filter([
                'minItems' => $this->minItems,
                'maxItems' => $this->maxItems,
                'uniqueItems' => $this->uniqueItems,
            ], fn ($v) => $v !== null)
        );
    }
}

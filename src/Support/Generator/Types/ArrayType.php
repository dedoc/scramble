<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Schema;

class ArrayType extends Type
{
    /** @var Type|Schema */
    public $items;

    /** @var Type|Schema */
    public $prefixItems = [];

    public $minItems = null;

    public $maxItems = null;

    public $additionalItems = null;

    public function __construct()
    {
        parent::__construct('array');

        $defaultMissingType = new StringType;
        $defaultMissingType->setAttribute('missing', true);

        $this->items = $defaultMissingType;
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

    public function setAdditionalItems($additionalItems)
    {
        $this->additionalItems = $additionalItems;

        return $this;
    }

    public function toArray()
    {
        $shouldOmitItems = $this->items->getAttribute('missing')
            && count($this->prefixItems);

        return array_merge(
            parent::toArray(),
            $shouldOmitItems ? [] : [
                'items' => $this->items->toArray(),
            ],
            $this->prefixItems ? [
                'prefixItems' => array_map(fn ($item) => $item->toArray(), $this->prefixItems),
            ] : [],
            array_filter([
                'minItems' => $this->minItems,
                'maxItems' => $this->maxItems,
                'additionalItems' => $this->additionalItems,
            ], fn ($v) => $v !== null)
        );
    }
}

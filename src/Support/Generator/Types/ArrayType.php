<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Schema;

class ArrayType extends Type
{
    /** @var Type|Schema */
    public $items;

    private $minItems = null;

    private $maxItems = null;

    public function __construct()
    {
        parent::__construct('array');
        $this->items = new StringType;
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

    public function toArray()
    {
        return array_merge(
            parent::toArray(),
            [
                'items' => $this->items->toArray(),
            ],
            array_filter([
                'minItems' => $this->minItems,
                'maxItems' => $this->maxItems,
            ], fn ($v) => $v !== null)
        );
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}

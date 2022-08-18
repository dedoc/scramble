<?php

namespace Dedoc\ApiDocs\Support\Generator\Types;

use Dedoc\ApiDocs\Support\Generator\Schema;

class ArrayType extends Type
{
    /** @var Type|Schema */
    private $items;

    public function __construct()
    {
        parent::__construct('array');
        $this->items = new StringType;
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'items' => $this->items->toArray(),
        ]);
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}

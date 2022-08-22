<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class AllOf extends Type
{
    /** @var Type[] */
    private $items;

    public function __construct()
    {
        parent::__construct('allOf');
        $this->items = [new StringType];
    }

    public function toArray()
    {
        return [
            'allOf' => array_map(
                fn ($item) => $item->toArray(),
                $this->items,
            )
        ];
    }

    public function setItems($items)
    {
        $this->items = array_filter($items);

        return $this;
    }
}

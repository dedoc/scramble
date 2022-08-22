<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class AnyOf extends Type
{
    /** @var Type[] */
    private $items;

    public function __construct()
    {
        parent::__construct('anyOf');
        $this->items = [new StringType];
    }

    public function toArray()
    {
        return [
            'anyOf' => array_map(
                fn ($item) => $item->toArray(),
                $this->items,
            ),
        ];
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}

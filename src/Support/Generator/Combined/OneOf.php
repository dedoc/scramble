<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class OneOf extends Type
{
    /** @var Type[] */
    private $items;

    public function __construct()
    {
        parent::__construct('oneOf');
        $this->items = [new StringType];
    }

    public function toArray()
    {
        return [
            'oneOf' => array_map(
                fn ($item) => $item->toArray(),
                $this->items,
            )
        ];
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}

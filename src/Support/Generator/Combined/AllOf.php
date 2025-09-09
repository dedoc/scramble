<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use InvalidArgumentException;

class AllOf extends Type
{
    /** @var Type[] */
    public $items;

    public function __construct()
    {
        parent::__construct('allOf');
        $this->items = [new StringType];
    }

    public function toArray()
    {
        $parentArray = parent::toArray();

        unset($parentArray['type']);

        return [
            ...$parentArray,
            'allOf' => array_map(
                fn ($item) => $item->toArray(),
                $this->items,
            ),
        ];
    }

    public function setItems($items)
    {
        if (collect($items)->contains(fn ($item) => ! $item instanceof Type)) {
            throw new InvalidArgumentException('All items should be instances of '.Type::class);
        }

        $this->items = $items;

        return $this;
    }
}

<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Schema;

class ArrayType extends Type
{
    /** @var Type|Schema */
    public $items;

    public function __construct()
    {
        parent::__construct('array');
        $this->items = new StringType;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'items' => $this->items->toArray(),
        ]);
    }

    public function setItems($items): self
    {
        $this->items = $items;

        return $this;
    }
}

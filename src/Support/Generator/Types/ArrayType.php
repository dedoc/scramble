<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\OpenApi;
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

    public function toArray(OpenApi $openApi)
    {
        return array_merge(parent::toArray($openApi), [
            'items' => $this->items->toArray($openApi),
        ]);
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}

<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\OpenApi;

class NumberType extends Type
{
    private $min = null;

    private $max = null;

    public function __construct($type = 'number')
    {
        parent::__construct($type);
    }

    public function setMin($min)
    {
        $this->min = $min;

        return $this;
    }

    public function setMax($max)
    {
        $this->max = $max;

        return $this;
    }

    public function toArray(OpenApi $openApi)
    {
        return array_merge(parent::toArray($openApi), array_filter([
            'minimum' => $this->min,
            'maximum' => $this->max,
        ], fn ($v) => $v !== null));
    }
}

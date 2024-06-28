<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class NumberType extends Type
{
    public $min = null;

    public $max = null;

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

    public function toArray()
    {
        return array_merge(parent::toArray(), array_filter([
            'minimum' => $this->min,
            'maximum' => $this->max,
        ], fn ($v) => $v !== null));
    }
}

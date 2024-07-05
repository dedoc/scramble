<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class StringType extends Type
{
    public $min = null;

    public $max = null;

    public function __construct()
    {
        parent::__construct('string');
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
            'minLength' => $this->min,
            'maxLength' => $this->max,
        ], fn ($v) => $v !== null));
    }
}

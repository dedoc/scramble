<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;

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

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        return array_merge($parentArray, array_filter([
            'minLength' => $this->min,
            'maxLength' => $this->max,
        ], fn ($v) => $v !== null));
    }
}

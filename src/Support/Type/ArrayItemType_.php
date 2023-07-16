<?php

namespace Dedoc\Scramble\Support\Type;

use LogicException;

class ArrayItemType_ extends AbstractType
{
    /** @var string|int|null */
    public $key;

    public Type $value;

    public bool $isOptional;

    public bool $shouldUnpack;

    public function __construct(
        $key,
        Type $value,
        bool $isOptional = false,
        bool $shouldUnpack = false
    ) {
        $this->key = $key;
        $this->value = $value;
        $this->isOptional = $isOptional;
        $this->shouldUnpack = $shouldUnpack;
    }

    public function nodes(): array
    {
        return ['value'];
    }

    public function isNumericKey()
    {
        return $this->key === null || is_numeric($this->key);
    }

    public function isSame(Type $type)
    {
        throw new LogicException('ArrayItemType_ should never be checked.');
    }

    public function toString(): string
    {
        return '';
    }
}

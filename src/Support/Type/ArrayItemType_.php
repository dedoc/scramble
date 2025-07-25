<?php

namespace Dedoc\Scramble\Support\Type;

use LogicException;

class ArrayItemType_ extends AbstractType
{
    public function __construct(
        /** @var string|int|null */
        public $key,
        public Type $value,
        public bool $isOptional = false,
        public bool $shouldUnpack = false,
        public ?Type $keyType = null,
    ) {}

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

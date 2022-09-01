<?php

namespace Dedoc\Scramble\Support\Type;

use Symfony\Component\Translation\Exception\LogicException;

class ArrayItemType_ extends AbstractType
{
    /** @var string|int|null */
    public $key;

    public Type $value;

    public bool $isOptional;

    public function __construct(
        $key,
        Type $value,
        bool $isOptional = false
    ) {
        $this->key = $key;
        $this->value = $value;
        $this->isOptional = $isOptional;
    }

    public function children(): array
    {
        return [$this->value];
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
        throw new LogicException('ArrayItemType_ should not be checked.');
    }

    public function toString(): string
    {
        return '';
    }
}

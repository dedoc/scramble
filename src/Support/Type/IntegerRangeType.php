<?php

namespace Dedoc\Scramble\Support\Type;

class IntegerRangeType extends IntegerType
{
    public ?int $min;

    public ?int $max;

    public function __construct(?int $min = null, ?int $max = null)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function isSame(Type $type)
    {
        return parent::isSame($type) && $type->toString() === $this->toString();
    }

    public function toString(): string
    {
        if ($this->min !== null || $this->max !== null) {
            $min = $this->min ?? 'min';
            $max = $this->max ?? 'max';

            return parent::toString()."<{$min}, {$max}>";
        }

        return parent::toString();
    }
}

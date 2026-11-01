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

    protected function acceptsAtomic(Type $otherType): bool
    {
        if (! $otherType instanceof IntegerType) {
            return false;
        }

        if ($this->min === null && $this->max === null) {
            return true;
        }

        if ($otherType instanceof Literal\LiteralIntegerType) {
            return ($this->min === null || $otherType->value >= $this->min)
                && ($this->max === null || $otherType->value <= $this->max);
        }

        if ($otherType instanceof self) {
            return ($this->min === null || ($otherType->min !== null && $otherType->min >= $this->min))
                && ($this->max === null || ($otherType->max !== null && $otherType->max <= $this->max));
        }

        return false;
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

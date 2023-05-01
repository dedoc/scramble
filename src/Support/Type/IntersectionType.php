<?php

namespace Dedoc\Scramble\Support\Type;

class IntersectionType extends AbstractType
{
    /** @var Type[] */
    public array $types;

    public function __construct(array $types)
    {
        $this->types = $types;
    }

    public function nodes(): array
    {
        return ['types'];
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && collect($this->types)->every(fn (Type $t, $i) => $t->isSame($type->types[$i]));
    }

    public function toString(): string
    {
        return implode('&', array_map(fn ($t) => $t->toString(), $this->types));
    }
}

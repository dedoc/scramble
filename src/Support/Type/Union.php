<?php

namespace Dedoc\Scramble\Support\Type;

use Illuminate\Support\Arr;

class Union extends AbstractType
{
    /**
     * @param  Type[]  $types
     */
    public function __construct(public array $types) {}

    public function nodes(): array
    {
        return ['types'];
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && collect($this->types)->every(fn (Type $t, $i) => $t->isSame($type->types[$i]));
    }

    /**
     * @param  Type|Type[]  $types
     */
    public static function wrap(...$types): Type
    {
        $types = Arr::wrap(...$types);
        $types = collect(array_values($types))
            ->unique(fn (Type $t) => $t->toString())
            ->values()
            ->all();

        if (! count($types)) {
            return new VoidType;
        }

        if (count($types) === 1) {
            return $types[0];
        }

        return new self($types);
    }

    public function toString(): string
    {
        return implode('|', array_map(fn ($t) => $t->toString(), $this->types));
    }
}

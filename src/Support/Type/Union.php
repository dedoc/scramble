<?php

namespace Dedoc\Scramble\Support\Type;

use Illuminate\Support\Arr;

class Union extends AbstractType
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

    public static function wrap(...$types)
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

        return new static($types);
    }

    public function toString(): string
    {
        return implode('|', array_map(fn ($t) => $t->toString(), $this->types));
    }
}

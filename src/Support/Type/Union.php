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

    public function widen(): Type
    {
        return app(TypeWidener::class)->widen($this->types)->mergeAttributes($this->attributes());
    }

    public function accepts(Type $otherType): bool
    {
        foreach ($this->types as $type) {
            if ($type->accepts($otherType)) {
                return true;
            }
        }

        return false;
    }

    public function acceptedBy(Type $otherType): bool
    {
        foreach ($this->types as $type) {
            if (! $type->acceptedBy($otherType)) {
                return false;
            }
        }

        return true;
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
